<?php

use App\Features\Teams\Enums\TeamRole;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot access the team systems page', function () {
    $team = Team::factory()->create();

    $this->get(route('teams.systems.edit', $team))
        ->assertRedirect(route('login'));
});

test('unauthorized team members cannot access the team systems page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->get(route('teams.systems.edit', $team))
        ->assertForbidden();
});

test('non-member users cannot access the team systems page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->get(route('teams.systems.edit', $team))
        ->assertForbidden();
});

test('authorized users can view the team systems page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

    $system = MonitoredSystem::query()->create([
        'team_id' => $team->id,
        'name' => 'Test System',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $parameter = Parameter::query()->create([
        'team_id' => $team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Test Parameter',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('teams.systems.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Systems')
            ->where('team.id', $team->id)
            ->where('team.name', $team->name)
            ->has('monitoredSystems', 1)
            ->where('monitoredSystems.0.id', $system->id)
            ->where('monitoredSystems.0.name', 'Test System')
            ->has('monitoredSystems.0.parameters', 1)
            ->where('monitoredSystems.0.parameters.0.id', $parameter->id)
            ->where('monitoredSystems.0.parameters.0.name', 'Test Parameter')
        );
});

test('authorized users can update team systems and parameters', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);

    $system = MonitoredSystem::query()->create([
        'team_id' => $team->id,
        'name' => 'Old System Name',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $parameter = Parameter::query()->create([
        'team_id' => $team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Old Param Name',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $payload = [
        'systems' => [
            [
                'id' => $system->id,
                'name' => 'New System Name',
                'is_active' => false,
                'sort_order' => 10,
                'parameters' => [
                    [
                        'id' => $parameter->id,
                        'name' => 'New Param Name',
                        'code' => 'new_code',
                        'tag' => 'new_tag',
                        'unit' => 'new_unit',
                        'decimals' => 3,
                        'sort_order' => 5,
                        'is_active' => false,
                        'alert_1_min' => 10.5,
                        'alert_1_max' => 20.0,
                        'alert_2_min' => null,
                        'alert_2_max' => null,
                        'alert_3_min' => null,
                        'alert_3_max' => null,
                        'alert_4_min' => null,
                        'alert_4_max' => null,
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(route('teams.systems.update', $team), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('monitored_systems', [
        'id' => $system->id,
        'name' => 'New System Name',
        'is_active' => false,
        'sort_order' => 10,
    ]);

    $this->assertDatabaseHas('parameters', [
        'id' => $parameter->id,
        'name' => 'New Param Name',
        'code' => 'new_code',
        'tag' => 'new_tag',
        'unit' => 'new_unit',
        'decimals' => 3,
        'sort_order' => 5,
        'is_active' => false,
        'alert_1_min' => 10.5,
        'alert_1_max' => 20.0,
    ]);
});
