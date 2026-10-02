<?php

use App\Models\DataEntryBatch;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'member']);
    $this->user->update(['current_team_id' => $this->team->id]);

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->team->members()->attach($this->admin, ['role' => 'admin']);
});

test('saving data entry automatically creates a data entry batch', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Gerador',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param1 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Vibração',
        'unit' => 'mm/s',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectedAt = '2026-08-19 15:00:00';

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $system->id,
            'collected_at' => $collectedAt,
            'values' => [
                ['parameter_id' => $param1->id, 'value' => 3.45],
            ],
            'comment' => 'Operação normal',
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('data_entry_batches', [
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'monitored_system_id' => $system->id,
        'status' => 'completed',
        'saved_values_count' => 1,
        'comment' => 'Operação normal',
    ]);
});

test('user can list history of submissions with pagination and system filter', function () {
    $system1 = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema 1',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $system2 = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema 2',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    DataEntryBatch::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'monitored_system_id' => $system1->id,
        'collected_at' => Carbon::now()->subHours(2),
        'collected_date' => Carbon::now()->toDateString(),
        'status' => 'completed',
        'saved_values_count' => 2,
        'parameter_ids' => [],
    ]);

    DataEntryBatch::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'monitored_system_id' => $system2->id,
        'collected_at' => Carbon::now()->subHour(),
        'collected_date' => Carbon::now()->toDateString(),
        'status' => 'completed',
        'saved_values_count' => 1,
        'parameter_ids' => [],
    ]);

    // All systems
    $responseAll = $this->actingAs($this->user)
        ->getJson(route('data-entry.history', ['current_team' => $this->team->slug]));

    $responseAll->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'batch_group_uuid',
                    'collected_at',
                    'responsible',
                    'user_name',
                    'status',
                    'systems_count',
                    'systems_names',
                    'systems' => [
                        '*' => [
                            'id',
                            'system_name',
                            'status',
                            'saved_values_count',
                            'can_revert',
                        ],
                    ],
                    'can_revert',
                ],
            ],
            'current_page',
            'last_page',
            'total',
        ]);

    // Filter by system 1
    $responseFiltered = $this->actingAs($this->user)
        ->getJson(route('data-entry.history', [
            'current_team' => $this->team->slug,
            'system_id' => $system1->id,
        ]));

    $responseFiltered->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.systems.0.system_name', 'Sistema 1');
});

test('user can revert own submission, deleting parameter values and preserving snapshot', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Calderaria',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Nível',
        'unit' => '%',
        'decimals' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectedAt = '2026-08-19 11:00:00';

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $param->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-19',
        'value' => 88.5,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    $batch = DataEntryBatch::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'monitored_system_id' => $system->id,
        'collected_at' => $collectedAt,
        'collected_date' => '2026-08-19',
        'status' => 'completed',
        'saved_values_count' => 1,
        'parameter_ids' => [$param->id],
        'comment' => 'Nível normal',
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('data-entry.history.destroy', [
            'current_team' => $this->team->slug,
            'batch' => $batch->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'batch_id' => $batch->id,
        ]);

    // Values should be deleted
    $this->assertDatabaseMissing('parameter_values', [
        'parameter_id' => $param->id,
        'measured_at' => $collectedAt,
    ]);

    // Batch status should be reverted with snapshot
    $freshBatch = $batch->fresh();
    $this->assertEquals('reverted', $freshBatch->status);
    $this->assertEquals($this->user->id, $freshBatch->reverted_by);
    $this->assertNotNull($freshBatch->reverted_at);
    $this->assertCount(1, $freshBatch->snapshot);
    $this->assertEquals('Nível', $freshBatch->snapshot[0]['name']);
    $this->assertEquals(88.5, $freshBatch->snapshot[0]['value']);
});

test('admin can revert submission created by another user', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Efluentes',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $batch = DataEntryBatch::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'monitored_system_id' => $system->id,
        'collected_at' => Carbon::now(),
        'collected_date' => Carbon::now()->toDateString(),
        'status' => 'completed',
        'saved_values_count' => 0,
        'parameter_ids' => [],
    ]);

    $response = $this->actingAs($this->admin)
        ->deleteJson(route('data-entry.history.destroy', [
            'current_team' => $this->team->slug,
            'batch' => $batch->id,
        ]));

    $response->assertOk();

    $this->assertEquals('reverted', $batch->fresh()->status);
    $this->assertEquals($this->admin->id, $batch->fresh()->reverted_by);
});

test('regular user cannot revert submission created by another user', function () {
    $otherUser = User::factory()->create();
    $this->team->members()->attach($otherUser, ['role' => 'member']);

    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema 1',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $batch = DataEntryBatch::create([
        'team_id' => $this->team->id,
        'user_id' => $otherUser->id,
        'monitored_system_id' => $system->id,
        'collected_at' => Carbon::now(),
        'collected_date' => Carbon::now()->toDateString(),
        'status' => 'completed',
        'saved_values_count' => 0,
        'parameter_ids' => [],
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('data-entry.history.destroy', [
            'current_team' => $this->team->slug,
            'batch' => $batch->id,
        ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['batch']);
});

test('user can fetch group details in background via show-group route', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Resfriamento',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Vazão Água',
        'unit' => 'm3/h',
        'decimals' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $groupUuid = (string) Str::uuid();
    $collectedAt = '2026-08-20 16:00:00';

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $param->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-20',
        'value' => 120.5,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    DataEntryBatch::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'monitored_system_id' => $system->id,
        'batch_group_uuid' => $groupUuid,
        'collected_at' => $collectedAt,
        'collected_date' => '2026-08-20',
        'status' => 'completed',
        'saved_values_count' => 1,
        'parameter_ids' => [$param->id],
        'responsible' => 'Operador João',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('data-entry.history.show-group', [
            'current_team' => $this->team->slug,
            'batch_group_uuid' => $groupUuid,
        ]));

    $response->assertOk()
        ->assertJsonPath('batch_group_uuid', $groupUuid)
        ->assertJsonPath('systems.0.responsible', 'Operador João')
        ->assertJsonPath('systems.0.system_name', 'Sistema Resfriamento')
        ->assertJsonPath('systems.0.parameters_data.0.name', 'Vazão Água')
        ->assertJsonPath('systems.0.parameters_data.0.value', 120.5);
});

test('user can revert whole batch group at once via destroy-group route', function () {
    $system1 = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema A',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $system2 = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema B',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $param1 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'name' => 'Pressão A',
        'unit' => 'bar',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param2 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'name' => 'Pressão B',
        'unit' => 'bar',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $groupUuid = (string) Str::uuid();
    $collectedAt = '2026-08-20 17:00:00';

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'parameter_id' => $param1->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-20',
        'value' => 10.5,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'parameter_id' => $param2->id,
        'measured_at' => $collectedAt,
        'measured_date' => '2026-08-20',
        'value' => 20.0,
        'source_type' => 'manual',
        'created_by' => $this->user->id,
    ]);

    DataEntryBatch::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'monitored_system_id' => $system1->id,
        'batch_group_uuid' => $groupUuid,
        'collected_at' => $collectedAt,
        'collected_date' => '2026-08-20',
        'status' => 'completed',
        'saved_values_count' => 1,
        'parameter_ids' => [$param1->id],
    ]);

    DataEntryBatch::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'monitored_system_id' => $system2->id,
        'batch_group_uuid' => $groupUuid,
        'collected_at' => $collectedAt,
        'collected_date' => '2026-08-20',
        'status' => 'completed',
        'saved_values_count' => 1,
        'parameter_ids' => [$param2->id],
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('data-entry.history.destroy-group', [
            'current_team' => $this->team->slug,
            'batch_group_uuid' => $groupUuid,
        ]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'batch_group_uuid' => $groupUuid,
            'reverted_count' => 2,
        ]);

    $this->assertDatabaseMissing('parameter_values', [
        'parameter_id' => $param1->id,
        'measured_at' => $collectedAt,
    ]);

    $this->assertDatabaseMissing('parameter_values', [
        'parameter_id' => $param2->id,
        'measured_at' => $collectedAt,
    ]);

    $this->assertEquals(0, DataEntryBatch::where('batch_group_uuid', $groupUuid)->where('status', 'completed')->count());
    $this->assertEquals(2, DataEntryBatch::where('batch_group_uuid', $groupUuid)->where('status', 'reverted')->count());
});

test('responsible is required when team require_data_entry_responsible is true', function () {
    $this->team->update(['require_data_entry_responsible' => true]);

    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Resp Test',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Parâmetro Resp',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Fails without responsible
    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $system->id,
            'collected_at' => '2026-08-20 18:00:00',
            'values' => [
                ['parameter_id' => $param->id, 'value' => 5],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['responsible']);

    // Succeeds with responsible
    $responseSuccess = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'responsible' => 'Maria Silva',
            'monitored_system_id' => $system->id,
            'collected_at' => '2026-08-20 18:00:00',
            'values' => [
                ['parameter_id' => $param->id, 'value' => 5],
            ],
        ]);

    $responseSuccess->assertOk();

    $this->assertDatabaseHas('data_entry_batches', [
        'team_id' => $this->team->id,
        'responsible' => 'Maria Silva',
    ]);
});

test('responsible is optional when team require_data_entry_responsible is false', function () {
    $this->team->update(['require_data_entry_responsible' => false]);

    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Opcional Resp',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Parâmetro Opcional',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('data-entry.store', ['current_team' => $this->team->slug]), [
            'monitored_system_id' => $system->id,
            'collected_at' => '2026-08-20 19:00:00',
            'values' => [
                ['parameter_id' => $param->id, 'value' => 10],
            ],
        ]);

    $response->assertOk();
});
