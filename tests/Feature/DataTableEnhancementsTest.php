<?php

use App\Features\DataTable\Services\DataTableDataBuilder;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\ParameterValueRevision;
use App\Models\ParameterValuesComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    $this->system = MonitoredSystem::query()->create(['team_id' => $this->team->id, 'name' => 'Sistema', 'is_active' => true]);
    $this->parameter = Parameter::query()->create(['team_id' => $this->team->id, 'monitored_system_id' => $this->system->id, 'name' => 'pH', 'decimals' => 2, 'sort_order' => 1, 'tag' => 'PH-01', 'alert_1_min' => 0, 'alert_1_max' => 20, 'is_active' => true]);
    $this->actingAs($this->user);
});

function storeEwmaReading(object $test, string $at, float $value, ?Parameter $parameter = null): ParameterValue
{
    $parameter ??= $test->parameter;

    return ParameterValue::query()->create(['team_id' => $parameter->team_id, 'monitored_system_id' => $parameter->monitored_system_id, 'parameter_id' => $parameter->id, 'measured_at' => $at, 'measured_date' => substr($at, 0, 10), 'value' => $value]);
}

function seedEwmaHistory(object $test): void
{
    for ($index = 0; $index < 25; $index++) {
        storeEwmaReading($test, Carbon::parse('2026-10-14 00:00')->addMinutes($index)->toDateTimeString(), 10 + $index % 2);
    }
}

function ewmaTableFilters(object $test, array $extra = []): array
{
    return array_merge(['system_ids' => [$test->system->id], 'start_date' => '2026-10-15', 'end_date' => '2026-10-16'], $extra);
}

test('EWMA defaults are available individually in the system panel and entry form', function () {
    expect($this->parameter->fresh()->ewma_lambda)->toBe(0.2)->and($this->parameter->fresh()->ewma_level)->toBe(3.0);
    $this->get(route('teams.systems.edit', $this->team))->assertOk()->assertInertia(fn ($page) => $page
        ->where('monitoredSystems.0.parameters.0.ewma_lambda', 0.2)->where('monitoredSystems.0.parameters.0.ewma_level', 3));
    $this->get(route('data-entry.index', $this->team))->assertOk()->assertInertia(fn ($page) => $page
        ->where('systems.0.parameters.0.ewma_lambda', 0.2)->where('systems.0.parameters.0.ewma_level', 3));
});

test('individual EWMA settings are saved without changing other parameters', function () {
    $other = Parameter::query()->create(['team_id' => $this->team->id, 'monitored_system_id' => $this->system->id, 'name' => 'Condutividade']);
    $parameter = $this->parameter->fresh()->toArray();
    $parameter['ewma_lambda'] = 0.4;
    $parameter['ewma_level'] = 2.5;
    $this->putJson(route('teams.systems.update', $this->team), ['systems' => [['id' => $this->system->id, 'name' => 'Sistema', 'is_active' => true, 'sort_order' => 1, 'parameters' => [$parameter]]]])->assertRedirect();
    expect($this->parameter->fresh()->ewma_lambda)->toBe(0.4)->and($this->parameter->fresh()->ewma_level)->toBe(2.5)
        ->and($other->fresh()->ewma_lambda)->toBe(0.2);
});

test('invalid EWMA settings are rejected', function (float $lambda, float $level) {
    $parameter = array_merge($this->parameter->fresh()->toArray(), ['ewma_lambda' => $lambda, 'ewma_level' => $level]);
    $this->putJson(route('teams.systems.update', $this->team), ['systems' => [['id' => $this->system->id, 'name' => 'Sistema', 'is_active' => true, 'sort_order' => 1, 'parameters' => [$parameter]]]])
        ->assertUnprocessable()->assertJsonValidationErrors($lambda <= 0 || $lambda > 1 ? 'systems.0.parameters.0.ewma_lambda' : 'systems.0.parameters.0.ewma_level');
})->with([[0.0, 3.0], [1.1, 3.0], [0.2, 0.0], [0.2, -1.0]]);

test('table evaluates only outside specification by default and entry preview returns the same EWMA', function () {
    seedEwmaHistory($this);
    storeEwmaReading($this, '2026-10-15 09:00:00', 30);
    storeEwmaReading($this, '2026-10-15 10:00:00', 12);
    storeEwmaReading($this, '2026-10-16 09:00:00', 1000);
    $result = app(DataTableDataBuilder::class)->build($this->team, ewmaTableFilters($this));
    $analysis = $result['rows'][0]['ewma'][$this->parameter->id];
    expect($analysis['history_count'])->toBe(20)->and($analysis['mean'])->toBe(10.5)->and($analysis['is_alert'])->toBeTrue()
        ->and($result['rows'][1]['ewma'])->toBe([])->and($result['parameters'][0]['tag'])->toBe('PH-01');
    $this->postJson(route('data-entry.ewma', $this->team), ['collected_at' => '2026-10-15 09:00:00', 'values' => [['parameter_id' => $this->parameter->id, 'value' => 30]]])
        ->assertOk()->assertJsonPath('results.'.$this->parameter->id.'.ewma', $analysis['ewma']);
});

test('table reuses chronological readings across days and honors custom lookback', function () {
    seedEwmaHistory($this);
    storeEwmaReading($this, '2026-10-15 09:00:00', 30);
    storeEwmaReading($this, '2026-10-16 09:00:00', 12);
    $result = app(DataTableDataBuilder::class)->build($this->team, ewmaTableFilters($this, ['ewma_all' => true, 'ewma_lookback' => 3]));
    $analysis = $result['rows'][1]['ewma'][$this->parameter->id];
    expect($analysis['history_count'])->toBe(3)->and($analysis['mean'])->toBe(17.0);
    $this->postJson(route('data-entry.ewma', $this->team), ['collected_at' => '2026-10-16 09:00:00', 'lookback' => 3, 'values' => [['parameter_id' => $this->parameter->id, 'value' => 12]]])
        ->assertOk()->assertJsonPath('results.'.$this->parameter->id.'.ewma', $analysis['ewma']);
});

test('table history is loaded in one bounded query for multiple parameters and dates', function () {
    seedEwmaHistory($this);
    $second = Parameter::query()->create(['team_id' => $this->team->id, 'monitored_system_id' => $this->system->id, 'name' => 'Temperatura']);
    for ($index = 0; $index < 30; $index++) {
        storeEwmaReading($this, Carbon::parse('2026-10-13')->addHours($index)->toDateTimeString(), 8 + $index % 3, $second);
        storeEwmaReading($this, Carbon::parse('2026-10-15')->addMinutes($index)->toDateTimeString(), 40 + $index);
    }
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'parameter_values')) {
            $queries[] = $query->sql;
        }
    });
    $result = app(DataTableDataBuilder::class)->build($this->team, ewmaTableFilters($this, ['ewma_all' => true]));
    expect($queries)->toHaveCount(2)->and($result['rows'][29]['ewma'][$this->parameter->id]['history_count'])->toBe(20);
});

test('turning both EWMA options off avoids history queries', function () {
    storeEwmaReading($this, '2026-10-15 09:00:00', 30);
    $result = app(DataTableDataBuilder::class)->build($this->team, ewmaTableFilters($this, ['ewma_all' => false, 'ewma_outside_limits' => false]));
    expect($result['rows'][0]['ewma'])->toBe([]);
});

test('EWMA cannot evaluate parameters from another unit', function () {
    $otherTeam = Team::factory()->create();
    $otherSystem = MonitoredSystem::query()->create(['team_id' => $otherTeam->id, 'name' => 'Outro']);
    $otherParameter = Parameter::query()->create(['team_id' => $otherTeam->id, 'monitored_system_id' => $otherSystem->id, 'name' => 'pH']);
    $this->postJson(route('data-entry.ewma', $this->team), ['collected_at' => '2026-10-15 09:00:00', 'values' => [['parameter_id' => $otherParameter->id, 'value' => 20]]])->assertUnprocessable();
    $this->getJson(route('data-table.data', ['current_team' => $this->team, 'system_ids' => [$otherSystem->id]]))->assertOk()->assertJsonCount(0, 'parameters');
});

test('editing a cell records its previous value and author and refreshes the history', function () {
    storeEwmaReading($this, '2026-10-15 09:00:00', 12);
    $this->putJson(route('data-table.cell.update', $this->team), ['parameter_id' => $this->parameter->id, 'timestamp' => '2026-10-15 09:00:00', 'value' => 25])->assertOk();
    $this->getJson(route('data-table.rows.details', ['current_team' => $this->team, 'timestamp' => '2026-10-15 09:00:00', 'system_ids' => [$this->system->id]]))
        ->assertOk()->assertJsonPath('history.0.changes.before', 12)->assertJsonPath('history.0.changes.after', 25)->assertJsonPath('history.0.user', $this->user->name);
    expect(ParameterValue::query()->first()->updated_by)->toBe($this->user->id);
});

test('comments can be saved read and cleared independently per system', function () {
    $second = MonitoredSystem::query()->create(['team_id' => $this->team->id, 'name' => 'Segundo']);
    foreach ([$this->system, $second] as $system) {
        $this->putJson(route('data-table.rows.comment', $this->team), ['monitored_system_id' => $system->id, 'timestamp' => '2026-10-15 09:00:00', 'comment' => 'Observação '.$system->id])->assertOk();
    }
    $this->putJson(route('data-table.rows.comment', $this->team), ['monitored_system_id' => $this->system->id, 'timestamp' => '2026-10-15 09:00:00', 'comment' => ''])->assertOk();
    expect(ParameterValuesComment::query()->count())->toBe(1)->and(ParameterValuesComment::query()->first()->monitored_system_id)->toBe($second->id);
    $this->getJson(route('data-table.rows.details', ['current_team' => $this->team, 'timestamp' => '2026-10-15 09:00:00', 'system_ids' => [$second->id]]))->assertOk()->assertJsonPath('comments.0.comment', 'Observação '.$second->id);
});

test('moving a row moves its comments and history and rejects overwriting another timestamp', function () {
    storeEwmaReading($this, '2026-10-15 09:00:00', 12);
    $this->putJson(route('data-table.rows.comment', $this->team), ['monitored_system_id' => $this->system->id, 'timestamp' => '2026-10-15 09:00:00', 'comment' => 'Antes'])->assertOk();
    $payload = ['parameter_ids' => [$this->parameter->id], 'old_timestamp' => '2026-10-15 09:00:00', 'new_timestamp' => '2026-10-16 10:00:00'];
    $this->putJson(route('data-table.rows.timestamp.update', $this->team), $payload)->assertOk();
    expect(ParameterValuesComment::query()->first()->measured_at->format('Y-m-d H:i'))->toBe('2026-10-16 10:00')
        ->and(ParameterValueRevision::query()->where('measured_at', '2026-10-16 10:00:00')->count())->toBe(2);
    storeEwmaReading($this, '2026-10-16 11:00:00', 15);
    $this->putJson(route('data-table.rows.timestamp.update', $this->team), array_merge($payload, ['old_timestamp' => $payload['new_timestamp'], 'new_timestamp' => '2026-10-16 11:00:00']))->assertUnprocessable();
});

test('deleting a row clears its comment and retains an audit record', function () {
    storeEwmaReading($this, '2026-10-15 09:00:00', 12);
    $this->putJson(route('data-table.rows.comment', $this->team), ['monitored_system_id' => $this->system->id, 'timestamp' => '2026-10-15 09:00:00', 'comment' => 'Observação'])->assertOk();
    $this->deleteJson(route('data-table.rows.destroy', $this->team), ['timestamp' => '2026-10-15 09:00:00', 'parameter_ids' => [$this->parameter->id]])->assertOk();
    expect(ParameterValuesComment::query()->count())->toBe(0)->and(ParameterValueRevision::query()->where('action', 'exclusão')->count())->toBe(1);
});

test('row comments and history reject systems from another team', function () {
    $team = Team::factory()->create();
    $system = MonitoredSystem::query()->create(['team_id' => $team->id, 'name' => 'Privado']);
    $this->putJson(route('data-table.rows.comment', $this->team), ['monitored_system_id' => $system->id, 'timestamp' => '2026-10-15 09:00:00', 'comment' => 'Inválido'])->assertUnprocessable();
    $this->getJson(route('data-table.rows.details', ['current_team' => $this->team, 'timestamp' => '2026-10-15 09:00:00', 'system_ids' => [$system->id]]))->assertUnprocessable();
});
