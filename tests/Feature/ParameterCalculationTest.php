<?php

use App\Features\Calculations\Services\ParameterCalculationService;
use App\Features\DataEntry\Actions\RevertDataEntryBatchAction;
use App\Features\DataEntry\Actions\StoreBatchDataEntryAction;
use App\Features\DataEntry\Actions\StoreDataEntryAction;
use App\Features\DataTable\Services\DataTableMutationService;
use App\Features\SpreadsheetImports\Actions\ExecuteSpreadsheetImportAction;
use App\Features\SpreadsheetImports\Actions\RevertSpreadsheetImportAction;
use App\Jobs\RecalculateParameters;
use App\Models\CalculationRule;
use App\Models\CalculationRun;
use App\Models\DataEntryBatch;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\ParameterValueRevision;
use App\Models\SpreadsheetImportBatch;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['is_admin' => true, 'current_team_id' => $this->team->id]);
    $this->system = MonitoredSystem::create(['team_id' => $this->team->id, 'name' => 'Principal']);
    $this->secondSystem = MonitoredSystem::create(['team_id' => $this->team->id, 'name' => 'Secundário']);
    $this->parameters = collect(['P', 'S', 'D', 'E'])->map(fn (string $name, int $index): Parameter => Parameter::create([
        'team_id' => $this->team->id, 'monitored_system_id' => $index === 1 ? $this->secondSystem->id : $this->system->id,
        'name' => $name, 'decimals' => 2,
    ]));
    [$this->p, $this->s, $this->d, $this->e] = $this->parameters->all();
    $this->rule = CalculationRule::create(['team_id' => $this->team->id, 'name' => 'Ciclo', 'type' => 'cycle',
        'primary_parameter_id' => $this->p->id, 'secondary_parameter_id' => $this->s->id, 'destination_parameter_id' => $this->d->id]);
    $this->at = '2026-10-20 10:00:00';
    $this->calculator = app(ParameterCalculationService::class);
});

function calculationInput(Team $team, User $user, Parameter $parameter, float|string|null $value, string $at = '2026-10-20 10:00:00'): array
{
    return app(StoreDataEntryAction::class)->execute($team, $user, $parameter->monitored_system_id, $at, [['parameter_id' => $parameter->id, 'value' => $value]]);
}

function calculationPayload(Parameter $p, Parameter $s, Parameter $d): array
{
    return ['name' => 'Regra', 'type' => 'cycle', 'primary_system_id' => $p->monitored_system_id,
        'primary_parameter_id' => $p->id, 'secondary_system_id' => $s->monitored_system_id, 'secondary_parameter_id' => $s->id,
        'destination_system_id' => $d->monitored_system_id, 'destination_parameter_id' => $d->id];
}

test('evaluates every formula', function (string $type, float $p, float $s, ?float $expected) {
    $this->rule->type = $type;
    $result = $this->calculator->calculate($this->rule, $p, $s);
    if ($expected === null) {
        expect($result)->toBeNull();
    } else {
        expect($result)->toEqualWithDelta($expected, 0.00000001);
    }
})->with([
    ['cycle', 12., 3., 4.], ['efficiency', 100., 20., 80.], ['net_loss', 12., 4., 4.],
    ['ph', 276., 9., 11.], ['percentage', 80., 20., 25.], ['ratio', 80., 20., 0.25],
    ['cycle', 0., 3., 0.], ['cycle', 3., 0., null], ['ratio', 0., 3., null],
    ['efficiency', 0., 3., null], ['percentage', 0., 3., null], ['net_loss', 3., 1., null],
    ['ph', 3., 9., null], ['ph', -3., 9., null], ['cycle', INF, 3., null],
]);

test('applies optional multipliers before and after the formula', function () {
    $this->rule->fill(['primary_multiplier' => 10, 'secondary_multiplier' => 2, 'result_multiplier' => 100]);
    expect($this->calculator->calculate($this->rule, 12, 3))->toBe(2000.0);
    $this->rule->result_multiplier = 0;
    expect($this->calculator->calculate($this->rule, 12, 3))->toBe(0.0);
    $this->rule->result_multiplier = 1e308;
    expect($this->calculator->calculate($this->rule, 12, 3))->toBeNull();
    $this->rule->primary_multiplier = 1e308;
    expect($this->calculator->calculate($this->rule, 12, 3))->toBeNull();
    expect($this->calculator->calculate($this->rule, null, 3))->toBeNull();
});

test('manual writes match the exact timestamp and work in either input order', function (bool $reverse) {
    calculationInput($this->team, $this->user, $reverse ? $this->s : $this->p, $reverse ? 3 : 12);
    expect(ParameterValue::where('parameter_id', $this->d->id)->exists())->toBeFalse();
    calculationInput($this->team, $this->user, $reverse ? $this->p : $this->s, $reverse ? 12 : 3, '2026-10-20 10:00:01');
    expect(ParameterValue::where('parameter_id', $this->d->id)->exists())->toBeFalse();
    calculationInput($this->team, $this->user, $reverse ? $this->p : $this->s, $reverse ? 12 : 3);
    $value = ParameterValue::where('parameter_id', $this->d->id)->sole();
    expect($value->value)->toBe(4.0)->and($value->source_type)->toBe('calculated')->and($value->calculation_rule_id)->toBe($this->rule->id);
})->with([false, true]);

test('chains calculations and removes invalid calculated descendants', function () {
    CalculationRule::create(['team_id' => $this->team->id, 'name' => 'Segundo', 'type' => 'ratio',
        'primary_parameter_id' => $this->s->id, 'secondary_parameter_id' => $this->d->id, 'destination_parameter_id' => $this->e->id]);
    calculationInput($this->team, $this->user, $this->p, 12);
    calculationInput($this->team, $this->user, $this->s, 3);
    expect(ParameterValue::where('parameter_id', $this->e->id)->sole()->value)->toEqualWithDelta(4 / 3, 0.000001);
    calculationInput($this->team, $this->user, $this->s, 0);
    expect(ParameterValue::whereIn('parameter_id', [$this->d->id, $this->e->id])->count())->toBe(0);
    expect(ParameterValueRevision::where('action', 'cálculo')->count())->toBe(4);
});

test('overwrites a manual destination but preserves it when inputs are invalid', function () {
    calculationInput($this->team, $this->user, $this->d, 99);
    calculationInput($this->team, $this->user, $this->s, 0);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->value)->toBe(99.0);
    calculationInput($this->team, $this->user, $this->p, 12);
    calculationInput($this->team, $this->user, $this->s, 3);
    $id = ParameterValue::where('parameter_id', $this->d->id)->sole()->id;
    calculationInput($this->team, $this->user, $this->p, 24);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->id)->toBe($id);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->value)->toBe(8.0);
    calculationInput($this->team, $this->user, $this->d, 88);
    calculationInput($this->team, $this->user, $this->s, 0);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->source_type)->toBe('manual');
});

test('batch entry calculates only after all systems and explicit destinations are saved', function () {
    $result = app(StoreBatchDataEntryAction::class)->execute($this->team, $this->user, $this->at, [
        ['monitored_system_id' => $this->secondSystem->id, 'values' => [['parameter_id' => $this->s->id, 'value' => 3]]],
        ['monitored_system_id' => $this->system->id, 'values' => [['parameter_id' => $this->p->id, 'value' => 12], ['parameter_id' => $this->d->id, 'value' => 999]]],
    ]);
    expect($result['total_values_saved'])->toBe(3);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->value)->toBe(4.0);
    expect(ParameterValueRevision::where('action', 'cálculo')->count())->toBe(1);
});

test('clearing and reverting manual input invalidate derived results', function (bool $revert) {
    calculationInput($this->team, $this->user, $this->p, 12);
    $result = calculationInput($this->team, $this->user, $this->s, 3);
    if ($revert) {
        app(RevertDataEntryBatchAction::class)->execute($this->team, $this->user, DataEntryBatch::findOrFail($result['batch_id']));
    } else {
        calculationInput($this->team, $this->user, $this->s, null);
    }
    expect(ParameterValue::where('parameter_id', $this->d->id)->exists())->toBeFalse();
})->with([false, true]);

test('table edits deletions and timestamp moves recalculate both timestamps', function () {
    calculationInput($this->team, $this->user, $this->p, 12);
    calculationInput($this->team, $this->user, $this->s, 3);
    $service = app(DataTableMutationService::class);
    $at = Carbon::parse($this->at);
    $service->updateValue($this->team, $this->user, $this->p, $at, 24);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->value)->toBe(8.0);
    $to = $at->copy()->addHour();
    $service->moveRow($this->team, $this->user, $at, $to, [$this->p->id, $this->s->id]);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->measured_at->equalTo($to))->toBeTrue();
    $service->deleteRow($this->team, $this->user, $to, [$this->s->id]);
    expect(ParameterValue::where('parameter_id', $this->d->id)->exists())->toBeFalse();
});

test('spreadsheet writes and reversions recalculate using the shared execution action', function () {
    calculationInput($this->team, $this->user, $this->p, 12);
    $template = SpreadsheetTemplate::create(['team_id' => $this->team->id, 'name' => 'Teste', 'config' => [], 'is_active' => true]);
    $items = [['system_id' => $this->s->monitored_system_id, 'parameter_id' => $this->s->id, 'final_value' => 3,
        'measured_at' => $this->at, 'measured_date' => '2026-10-20']];
    $result = app(ExecuteSpreadsheetImportAction::class)->execute($this->team, $this->user, $template, 'teste.xlsx', null, null, $items);
    expect($result['saved_count'])->toBe(1);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->value)->toBe(4.0);
    app(RevertSpreadsheetImportAction::class)->execute(SpreadsheetImportBatch::findOrFail($result['batch_id']), $this->user);
    expect(ParameterValue::where('parameter_id', $this->d->id)->exists())->toBeFalse();
});

test('rejects duplicate destinations and cyclic dependencies', function (bool $cycle) {
    $payload = calculationPayload($this->d, $this->s, $cycle ? $this->p : $this->d);
    $this->actingAs($this->user)->post(route('calculations.store', $this->team), $payload)->assertSessionHasErrors('destination_parameter_id');
    expect(CalculationRule::count())->toBe(1);
})->with([false, true]);

test('only administrators may access every calculation endpoint', function () {
    $this->user->update(['is_admin' => false]);
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    $this->actingAs($this->user);
    $this->get(route('calculations.index', $this->team))->assertForbidden();
    $this->post(route('calculations.store', $this->team), [])->assertForbidden();
    $this->put(route('calculations.update', [$this->team, $this->rule]), [])->assertForbidden();
    $this->delete(route('calculations.destroy', [$this->team, $this->rule]))->assertForbidden();
    $this->post(route('calculations.recalculate', [$this->team, $this->rule]), [])->assertForbidden();
});

test('admin manages rules and historical results survive deletion', function () {
    calculationInput($this->team, $this->user, $this->p, 12);
    calculationInput($this->team, $this->user, $this->s, 3);
    $this->actingAs($this->user)->get(route('calculations.index', $this->team))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Calculations/Index')->has('rules', 1)->has('systems', 2));
    $this->put(route('calculations.update', [$this->team, $this->rule]), calculationPayload($this->p, $this->s, $this->d) + ['result_multiplier' => 2])->assertSessionHasNoErrors();
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->value)->toBe(4.0);
    $this->delete(route('calculations.destroy', [$this->team, $this->rule]))->assertSessionHasNoErrors();
    expect(CalculationRule::count())->toBe(0);
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->calculation_rule_id)->toBe($this->rule->id);
    $this->post(route('calculations.store', $this->team), calculationPayload($this->p, $this->s, $this->d))->assertSessionHasNoErrors();
    expect(CalculationRule::count())->toBe(1);
});

test('rejects cross unit systems parameters and rule access', function () {
    $other = Team::factory()->create();
    $this->actingAs($this->user)->post(route('calculations.store', $other), calculationPayload($this->p, $this->s, $this->d))->assertSessionHasErrors();
    $this->delete(route('calculations.destroy', [$other, $this->rule]))->assertNotFound();
    $counts = DB::transaction(function () use ($other): array {
        $this->calculator->lock($other);

        return $this->calculator->execute($other, [$this->p->id], [$this->at], $this->user);
    });
    expect($counts)->toBe(['created' => 0, 'updated' => 0, 'removed' => 0]);
});

test('historical recalculation includes prerequisites descendants and stale destinations', function () {
    Queue::fake();
    calculationInput($this->team, $this->user, $this->p, 12);
    calculationInput($this->team, $this->user, $this->s, 3);
    $child = CalculationRule::create(['team_id' => $this->team->id, 'name' => 'Filho', 'type' => 'ratio',
        'primary_parameter_id' => $this->s->id, 'secondary_parameter_id' => $this->d->id, 'destination_parameter_id' => $this->e->id]);
    $this->rule->update(['result_multiplier' => 2]);
    $this->actingAs($this->user)->post(route('calculations.recalculate', [$this->team, $child]), ['start_date' => '2026-10-20', 'end_date' => '2026-10-20'])->assertSessionHasNoErrors();
    Queue::assertPushed(RecalculateParameters::class);
    $run = CalculationRun::sole();
    (new RecalculateParameters($run->id))->handle($this->calculator);
    expect($run->fresh()->status)->toBe('completed');
    expect(ParameterValue::where('parameter_id', $this->d->id)->sole()->value)->toBe(8.0);
    expect(ParameterValue::where('parameter_id', $this->e->id)->sole()->value)->toEqualWithDelta(8 / 3, 0.000001);
    ParameterValue::whereIn('parameter_id', [$this->p->id, $this->s->id])->delete();
    $run2 = CalculationRun::create(['team_id' => $this->team->id, 'calculation_rule_id' => $this->rule->id,
        'user_id' => $this->user->id, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'rule_ids' => [$this->rule->id, $child->id]]);
    (new RecalculateParameters($run2->id))->handle($this->calculator);
    expect($run2->fresh()->removed_count)->toBe(2);
});

test('recalculation validates range prevents overlapping runs and reports failure', function () {
    Queue::fake();
    $url = route('calculations.recalculate', [$this->team, $this->rule]);
    $this->actingAs($this->user)->post($url, ['start_date' => '2026-10-21', 'end_date' => '2026-10-20'])->assertSessionHasErrors('end_date');
    $this->post($url, ['start_date' => '2026-10-20', 'end_date' => '2026-10-21'])->assertSessionHasNoErrors();
    $this->post($url, ['start_date' => '2026-10-20', 'end_date' => '2026-10-21'])->assertSessionHasErrors('calculation');
    $this->delete(route('calculations.destroy', [$this->team, $this->rule]))->assertSessionHasErrors('calculation');
    $run = CalculationRun::sole();
    (new RecalculateParameters($run->id))->failed(new RuntimeException('Test failure'));
    expect($run->fresh()->status)->toBe('failed');
});

test('rolls back input and calculated output together', function () {
    calculationInput($this->team, $this->user, $this->s, 3);
    try {
        DB::transaction(function (): void {
            calculationInput($this->team, $this->user, $this->p, 12);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect(ParameterValue::whereIn('parameter_id', [$this->p->id, $this->d->id])->count())->toBe(0);
});

test('historical jobs checkpoint chunks and respect inclusive date boundaries', function () {
    Queue::fake();
    $rows = [];
    for ($index = 0; $index < 205; $index++) {
        $at = $index === 204 ? '2026-10-20 23:59:59' : Carbon::parse('2026-10-20 00:00:00')->addMinutes($index)->toDateTimeString();
        foreach ([[$this->p, 12], [$this->s, 3]] as [$parameter, $value]) {
            $rows[] = ['team_id' => $this->team->id, 'monitored_system_id' => $parameter->monitored_system_id,
                'parameter_id' => $parameter->id, 'measured_at' => $at, 'measured_date' => '2026-10-20', 'value' => $value, 'source_type' => 'manual'];
        }
    }
    ParameterValue::insert($rows);
    calculationInput($this->team, $this->user, $this->p, 12, '2026-10-21 00:00:00');
    $run = CalculationRun::create(['team_id' => $this->team->id, 'calculation_rule_id' => $this->rule->id,
        'user_id' => $this->user->id, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'rule_ids' => [$this->rule->id]]);
    $job = new RecalculateParameters($run->id);
    $job->handle($this->calculator);
    expect($run->fresh()->processed_count)->toBe(100)->and($run->fresh()->status)->toBe('running');
    $job->handle($this->calculator);
    $job->handle($this->calculator);
    $job->handle($this->calculator);
    expect($run->fresh()->processed_count)->toBe(205)->and($run->fresh()->created_count)->toBe(205)->and($run->fresh()->status)->toBe('completed');
    expect(ParameterValue::where('parameter_id', $this->d->id)->count())->toBe(205);
});

test('database uniqueness prevents racing rules with the same destination', function () {
    expect(fn () => CalculationRule::create(['team_id' => $this->team->id, 'name' => 'Duplicado', 'type' => 'ratio',
        'primary_parameter_id' => $this->s->id, 'secondary_parameter_id' => $this->p->id, 'destination_parameter_id' => $this->d->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('queue dispatch failure releases the unit for another recalculation', function () {
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
    $this->actingAs($this->user)->post(route('calculations.recalculate', [$this->team, $this->rule]),
        ['start_date' => '2026-10-20', 'end_date' => '2026-10-20'])->assertSessionHasErrors('calculation');
    expect(CalculationRun::sole()->status)->toBe('failed');
});
