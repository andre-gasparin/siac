<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\ParameterValuesComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    $this->user->update(['current_team_id' => $this->team->id]);
});

test('user can view data entry page for current team', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Água Clarificada',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $paramA = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'pH',
        'unit' => 'pH',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
        'alert_1_min' => 6.5,
        'alert_1_max' => 8.5,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('data-entry.index', ['current_team' => $this->team->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('DataEntry/Index')
            ->has('systems', 1)
            ->where('systems.0.name', 'Sistema Água Clarificada')
            ->where('systems.0.parameters.0.name', 'pH')
            ->where('systems.0.parameters.0.alert_1_min', 6.5)
            ->where('systems.0.parameters.0.alert_1_max', 8.5)
            ->has('currentTeam')
            ->has('currentTimestamp')
            ->has('defaultCollectionDate')
            ->has('defaultCollectionTime')
            ->has('initialEntries')
        );
});

test('user cannot view data entry page if not a team member', function () {
    $otherTeam = Team::factory()->create();

    $response = $this->actingAs($this->user)
        ->get(route('data-entry.index', ['current_team' => $otherTeam->slug]));

    $response->assertForbidden();
});

test('user can store parameter values for a monitored system', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Resfriamento',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param1 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Temperatura',
        'unit' => '°C',
        'decimals' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param2 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Condutividade',
        'unit' => 'µS/cm',
        'decimals' => 0,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $collectedAt = '2026-08-19 14:30:00';

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $system->id,
            'collected_at' => $collectedAt,
            'values' => [
                ['parameter_id' => $param1->id, 'value' => 28.5],
                ['parameter_id' => $param2->id, 'value' => 450],
            ],
            'comment' => 'Coleta de rotina no turno da tarde',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'saved_count' => 2,
                'has_comment' => true,
            ],
        ]);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $param1->id,
        'measured_at' => $collectedAt,
        'value' => 28.5,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $param2->id,
        'measured_at' => $collectedAt,
        'value' => 450,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    $this->assertDatabaseHas('parameter_values_comments', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'measured_at' => $collectedAt,
        'comment' => 'Coleta de rotina no turno da tarde',
        'created_by' => $this->user->id,
    ]);
});

test('empty parameters are not saved but filled parameters are saved', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Caldeira 1',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param1 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Pressão',
        'unit' => 'bar',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param2 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Fosfato',
        'unit' => 'ppm',
        'decimals' => 2,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $collectedAt = '2026-08-19 10:00:00';

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $system->id,
            'collected_at' => $collectedAt,
            'values' => [
                ['parameter_id' => $param1->id, 'value' => 12.4],
                ['parameter_id' => $param2->id, 'value' => null],
            ],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'saved_count' => 1,
                'has_comment' => false,
            ],
        ]);

    $this->assertDatabaseHas('parameter_values', [
        'parameter_id' => $param1->id,
        'value' => 12.4,
    ]);

    $this->assertDatabaseMissing('parameter_values', [
        'parameter_id' => $param2->id,
        'measured_at' => $collectedAt,
    ]);
});

test('fails when trying to save for a system belonging to another team', function () {
    $otherTeam = Team::factory()->create();
    $otherSystem = MonitoredSystem::create([
        'team_id' => $otherTeam->id,
        'name' => 'Outro Sistema',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $otherParam = Parameter::create([
        'team_id' => $otherTeam->id,
        'monitored_system_id' => $otherSystem->id,
        'name' => 'Outro Param',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $otherSystem->id,
            'collected_at' => '2026-08-19 14:00:00',
            'values' => [
                ['parameter_id' => $otherParam->id, 'value' => 10],
            ],
            'comment' => 'Tentativa inválida',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['monitored_system_id']);
});

test('fails when no parameters and no comment are provided', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema 1',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'pH',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $system->id,
            'collected_at' => '2026-08-19 14:00:00',
            'values' => [
                ['parameter_id' => $param->id, 'value' => null],
            ],
            'comment' => '',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['values']);
});

test('updating an existing parameter value on the same measured_at updates the record', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Osmose',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Vazão',
        'unit' => 'm3/h',
        'decimals' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectedAt = '2026-08-19 08:00:00';

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $param->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-19',
        'value' => 50.0,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $system->id,
            'collected_at' => $collectedAt,
            'values' => [
                ['parameter_id' => $param->id, 'value' => 55.5],
            ],
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('parameter_values', [
        'parameter_id' => $param->id,
        'measured_at' => $collectedAt,
        'value' => 55.5,
    ]);

    $this->assertEquals(1, ParameterValue::where('parameter_id', $param->id)->where('measured_at', $collectedAt)->count());
});

test('user can fetch existing entries and comments for a specific collected_at timestamp', function () {
    $systemA = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema A',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $systemB = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema B',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $paramA = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $systemA->id,
        'name' => 'Temperatura A',
        'unit' => '°C',
        'decimals' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectedAt = '2026-08-20 08:30:00';

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $systemA->id,
        'parameter_id' => $paramA->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-20',
        'value' => 25.5,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('data-entry.entries', [
            'current_team' => $this->team->slug,
            'collected_at' => $collectedAt,
        ]));

    $response->assertOk()
        ->assertJson([
            'systems_with_data' => [$systemA->id],
            'values' => [
                (string) $paramA->id => 25.5,
            ],
            'collected_at' => $collectedAt,
        ]);
});

test('editing entries removes cleared parameter values and deleted comments', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Filtração',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param1 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Pressão Diferencial',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param2 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Turbidez',
        'decimals' => 2,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $collectedAt = '2026-08-20 09:00:00';

    // Seed existing measurements and comment
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $param1->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-20',
        'value' => 1.2,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $param2->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-20',
        'value' => 0.45,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    ParameterValuesComment::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-20',
        'comment' => 'Comentário original',
        'created_by' => $this->user->id,
    ]);

    // Now edit: keep param1 updated to 1.5, clear param2 (null), and clear comment (empty string)
    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $system->id,
            'collected_at' => $collectedAt,
            'values' => [
                ['parameter_id' => $param1->id, 'value' => 1.5],
                ['parameter_id' => $param2->id, 'value' => null],
            ],
            'comment' => '',
        ]);

    $response->assertOk();

    // Param 1 updated
    $this->assertDatabaseHas('parameter_values', [
        'parameter_id' => $param1->id,
        'measured_at' => $collectedAt,
        'value' => 1.5,
    ]);

    // Param 2 removed
    $this->assertDatabaseMissing('parameter_values', [
        'parameter_id' => $param2->id,
        'measured_at' => $collectedAt,
    ]);

    // Comment removed
    $this->assertDatabaseMissing('parameter_values_comments', [
        'monitored_system_id' => $system->id,
        'measured_at' => $collectedAt,
    ]);
});

test('user can store batch parameter values for multiple monitored systems in a single transaction', function () {
    $system1 = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema 1',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param1 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'name' => 'Temperatura',
        'unit' => '°C',
        'decimals' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $system2 = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema 2',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $param2 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'name' => 'Pressão',
        'unit' => 'bar',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectedAt = '2026-08-24 10:00:00';

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store-batch', ['current_team' => $this->team->slug]), [
            'collected_at' => $collectedAt,
            'systems' => [
                [
                    'monitored_system_id' => $system1->id,
                    'values' => [
                        ['parameter_id' => $param1->id, 'value' => 25.5],
                    ],
                    'comment' => 'Obs Sistema 1',
                ],
                [
                    'monitored_system_id' => $system2->id,
                    'values' => [
                        ['parameter_id' => $param2->id, 'value' => 3.42],
                    ],
                    'comment' => 'Obs Sistema 2',
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'total_systems_saved' => 2,
                'total_values_saved' => 2,
            ],
        ]);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'parameter_id' => $param1->id,
        'measured_at' => $collectedAt,
        'value' => 25.5,
    ]);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'parameter_id' => $param2->id,
        'measured_at' => $collectedAt,
        'value' => 3.42,
    ]);

    $this->assertDatabaseHas('parameter_values_comments', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'measured_at' => $collectedAt,
        'comment' => 'Obs Sistema 1',
    ]);

    $this->assertDatabaseHas('parameter_values_comments', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'measured_at' => $collectedAt,
        'comment' => 'Obs Sistema 2',
    ]);

    // Check DataEntryBatch was created for each system
    $this->assertDatabaseHas('data_entry_batches', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'collected_at' => $collectedAt,
        'status' => 'completed',
    ]);

    $this->assertDatabaseHas('data_entry_batches', [
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'collected_at' => $collectedAt,
        'status' => 'completed',
    ]);
});

test('user cannot store batch if all systems are empty', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Vazio',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Vazão',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store-batch', ['current_team' => $this->team->slug]), [
            'collected_at' => '2026-08-24 10:00:00',
            'systems' => [
                [
                    'monitored_system_id' => $system->id,
                    'values' => [
                        ['parameter_id' => $param->id, 'value' => null],
                    ],
                    'comment' => '',
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['systems']);
});
