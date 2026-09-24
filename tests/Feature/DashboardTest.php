<?php

use App\Models\Dashboard;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    $this->user->update(['current_team_id' => $this->team->id]);
});

test('user can list and create dashboards for current team', function () {
    $response = $this->actingAs($this->user)
        ->get(route('dashboards.index', ['current_team' => $this->team->slug]));

    $response->assertOk();

    $createResponse = $this->actingAs($this->user)
        ->post(route('dashboards.store', ['current_team' => $this->team->slug]), [
            'title' => 'Dashboard Operacional',
            'is_public' => true,
        ]);

    $this->assertDatabaseHas('dashboards', [
        'title' => 'Dashboard Operacional',
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'is_public' => true,
    ]);
});

test('user can update component grid configuration in bulk via AJAX', function () {
    $dashboard = Dashboard::create([
        'title' => 'Test Dashboard',
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'is_public' => false,
    ]);

    $component1 = $dashboard->components()->create([
        'type' => 'indicator',
        'grid_config' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2],
        'settings' => ['title' => 'Indicator 1'],
    ]);

    $component2 = $dashboard->components()->create([
        'type' => 'chart',
        'grid_config' => ['x' => 3, 'y' => 0, 'w' => 6, 'h' => 4],
        'settings' => ['title' => 'Chart 1'],
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson(route('dashboards.grid.update', [
            'current_team' => $this->team->slug,
            'dashboard' => $dashboard->id,
        ]), [
            'components' => [
                ['id' => $component1->id, 'grid_config' => ['x' => 0, 'y' => 2, 'w' => 4, 'h' => 3]],
                ['id' => $component2->id, 'grid_config' => ['x' => 4, 'y' => 2, 'w' => 8, 'h' => 5]],
            ],
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertEquals(['x' => 0, 'y' => 2, 'w' => 4, 'h' => 3], $component1->fresh()->grid_config);
    $this->assertEquals(['x' => 4, 'y' => 2, 'w' => 8, 'h' => 5], $component2->fresh()->grid_config);
});

test('dashboard owner can update component settings', function () {
    $dashboard = Dashboard::create([
        'title' => 'Dashboard editável',
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'is_public' => false,
    ]);
    $component = $dashboard->components()->create([
        'type' => 'indicator',
        'grid_config' => ['x' => 20, 'y' => 20, 'w' => 340, 'h' => 200],
        'settings' => ['title' => 'Título anterior', 'aggregation' => 'avg'],
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('dashboards.components.update', [
            'current_team' => $this->team->slug,
            'dashboard' => $dashboard->id,
            'component' => $component->id,
        ]), [
            'settings' => ['title' => 'Título atualizado', 'aggregation' => 'max'],
        ]);

    $response->assertOk()
        ->assertJsonPath('settings.title', 'Título atualizado');
    expect($component->fresh()->settings)->toMatchArray([
        'title' => 'Título atualizado',
        'aggregation' => 'max',
    ]);
});

test('dashboard owner can delete a component', function () {
    $dashboard = Dashboard::create([
        'title' => 'Dashboard editável',
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'is_public' => false,
    ]);
    $component = $dashboard->components()->create([
        'type' => 'text',
        'grid_config' => ['x' => 20, 'y' => 20, 'w' => 340, 'h' => 200],
        'settings' => ['title' => 'Texto temporário'],
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('dashboards.components.destroy', [
            'current_team' => $this->team->slug,
            'dashboard' => $dashboard->id,
            'component' => $component->id,
        ]));

    $response->assertOk()
        ->assertJson(['success' => true]);
    $this->assertModelMissing($component);
});

test('component data endpoint returns aggregated parameter values for indicator with 7-day default filter', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Caldera',
        'code' => 'SYS-01',
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Vazão de Água',
        'code' => 'P-01',
        'tag' => 'TAG-VAZAO',
        'unit' => 'm3/h',
        'decimals' => 2,
        'is_active' => true,
    ]);

    // Create parameter values within last 7 days
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => Carbon::now()->subDays(2),
        'measured_date' => Carbon::now()->subDays(2)->toDateString(),
        'value' => 100.0,
        'source_type' => 'manual',
    ]);

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => Carbon::now()->subDays(1),
        'measured_date' => Carbon::now()->subDays(1)->toDateString(),
        'value' => 200.0,
        'source_type' => 'manual',
    ]);

    $dashboard = Dashboard::create([
        'title' => 'Dashboard Data Test',
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'is_public' => false,
    ]);

    $component = $dashboard->components()->create([
        'type' => 'indicator',
        'grid_config' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2],
        'settings' => [
            'parameter_id' => $parameter->id,
            'aggregation' => 'avg',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('dashboards.components.data', [
            'current_team' => $this->team->slug,
            'component' => $component->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'id' => $component->id,
            'type' => 'indicator',
            'value' => 150.0,
            'unit' => 'm3/h',
            'aggregation' => 'avg',
            'parameter_id' => $parameter->id,
            'parameter_name' => 'Vazão de Água',
            'system_name' => 'Sistema Caldera',
            'parameter_display_name' => 'Sistema Caldera - Vazão de Água',
        ]);
});

test('component data endpoint includes system and parameter context for chart series', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Linha de Mistura',
        'code' => 'SYS-LM',
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Sílica',
        'code' => 'P-SILICA',
        'tag' => 'TAG-SILICA',
        'unit' => 'mg/L',
        'decimals' => 2,
        'is_active' => true,
    ]);

    $dashboard = Dashboard::create([
        'title' => 'Dashboard do gráfico',
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'is_public' => false,
    ]);

    $component = $dashboard->components()->create([
        'type' => 'chart',
        'grid_config' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
        'settings' => [
            'series' => [[
                'parameter_id' => $parameter->id,
                'label' => 'LM 1',
            ]],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('dashboards.components.data', [
            'current_team' => $this->team->slug,
            'component' => $component->id,
        ]));

    $response->assertOk()
        ->assertJsonPath('series.0.parameter_id', $parameter->id)
        ->assertJsonPath('series.0.label', 'LM 1')
        ->assertJsonPath('series.0.parameter_name', 'Sílica')
        ->assertJsonPath('series.0.system_name', 'Linha de Mistura')
        ->assertJsonPath(
            'series.0.parameter_display_name',
            'Linha de Mistura - Sílica',
        );
});

test('chart data ignores malformed persisted series settings', function (mixed $seriesSettings) {
    $dashboard = Dashboard::create([
        'title' => 'Dashboard com configuração legada',
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'is_public' => false,
    ]);

    $component = $dashboard->components()->create([
        'type' => 'chart',
        'grid_config' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
        'settings' => [
            'series' => $seriesSettings,
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('dashboards.components.data', [
            'current_team' => $this->team->slug,
            'component' => $component->id,
        ]));

    $response->assertOk()
        ->assertJsonPath('series', []);
})->with([
    'scalar value' => ['invalid series'],
    'invalid list items' => [[['invalid series'], ['parameter_id' => null]]],
]);

test('parameter search matches multi-word query across system name and parameter name', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Água de alimentação',
        'code' => 'SYS-03',
        'is_active' => true,
    ]);

    Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'pH',
        'code' => 'PH-01',
        'tag' => 'TAG-PH',
        'unit' => 'pH',
        'decimals' => 2,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('parameters.search', [
            'current_team' => $this->team->slug,
            'q' => 'Aliment ph',
        ]));

    $response->assertOk()
        ->assertJsonFragment([
            'display_name' => 'Água de alimentação - pH',
        ]);
});
