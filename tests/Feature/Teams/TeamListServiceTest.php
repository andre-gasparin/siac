<?php

use App\Features\Teams\Enums\TeamRole;
use App\Features\Teams\Services\TeamListService;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('team list service normalizes filters and includes seven days of activity', function () {
    Carbon::setTestNow('2026-07-28 12:00:00');

    $owner = User::factory()->create(['name' => 'Feature Owner']);
    $team = Team::factory()->create(['name' => 'Feature Unit']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $system = MonitoredSystem::query()->create([
        'team_id' => $team->id,
        'name' => 'Feature System',
    ]);
    $parameter = Parameter::query()->create([
        'team_id' => $team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Feature Parameter',
    ]);

    ParameterValue::query()->create([
        'team_id' => $team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => '2026-07-28 10:00:00',
        'measured_date' => '2026-07-28',
        'value' => 10,
    ]);

    $request = Request::create('/unidades', parameters: [
        'status' => 'unsupported',
        'search' => ' Feature Unit ',
        'role' => 'unsupported',
    ]);
    $service = app(TeamListService::class);
    $filters = $service->filters($request);
    $teams = $service->paginate($filters);

    expect($filters)->toBe([
        'status' => 'active',
        'search' => 'Feature Unit',
        'role' => 'all',
    ])->and($teams->total())->toBe(1)
        ->and($teams->items()[0]['name'])->toBe('Feature Unit')
        ->and($teams->items()[0]['roleLabel'])->toBe('Feature Owner')
        ->and($teams->items()[0]['parameterValueActivity'])->toHaveCount(7)
        ->and($teams->items()[0]['parameterValueActivity'][6])->toBe([
            'date' => '2026-07-28',
            'label' => '28/07/2026',
            'count' => 1,
        ]);
});

test('team list service returns both active and inactive teams when status is all', function () {
    $activeTeam = Team::factory()->create(['name' => 'Active Unit Alpha', 'is_active' => true]);
    $inactiveTeam = Team::factory()->create(['name' => 'Inactive Unit Beta', 'is_active' => false]);

    $service = app(TeamListService::class);
    $filters = $service->filters(Request::create('/unidades', parameters: ['status' => 'all']));
    $teams = $service->paginate($filters);
    $names = collect($teams->items())->pluck('name')->all();

    expect($filters['status'])->toBe('all')
        ->and($names)->toContain($activeTeam->name)
        ->and($names)->toContain($inactiveTeam->name);
});

test('team list service returns only inactive teams when status is inactive', function () {
    $activeTeam = Team::factory()->create(['name' => 'Active Unit Gamma', 'is_active' => true]);
    $inactiveTeam = Team::factory()->create(['name' => 'Inactive Unit Delta', 'is_active' => false]);

    $service = app(TeamListService::class);
    $filters = $service->filters(Request::create('/unidades', parameters: ['status' => 'inactive']));
    $teams = $service->paginate($filters);
    $names = collect($teams->items())->pluck('name')->all();

    expect($filters['status'])->toBe('inactive')
        ->and($names)->toContain($inactiveTeam->name)
        ->and($names)->not->toContain($activeTeam->name);
});
