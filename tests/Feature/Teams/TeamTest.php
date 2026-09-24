<?php

use App\Features\Teams\Enums\TeamRole;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('the teams index page can be rendered', function () {
    Carbon::setTestNow('2026-07-02 10:00:00');

    $user = User::factory()->create(['is_admin' => true, 'name' => 'Zed Admin']);
    $owner = User::factory()->create(['name' => 'Outside Owner']);
    $team = Team::factory()->create(['name' => 'Outside Unit']);
    $monitoredSystem = MonitoredSystem::query()->create([
        'team_id' => $team->id,
        'name' => 'Primary system',
    ]);
    $parameter = Parameter::query()->create([
        'team_id' => $team->id,
        'monitored_system_id' => $monitoredSystem->id,
        'name' => 'Temperature',
        'code' => 'temp',
    ]);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    foreach (['10:00:00', '10:01:00', '10:02:00'] as $time) {
        ParameterValue::query()->create([
            'team_id' => $team->id,
            'monitored_system_id' => $monitoredSystem->id,
            'parameter_id' => $parameter->id,
            'measured_at' => "2026-07-02 {$time}",
            'measured_date' => '2026-07-02',
            'value' => 1,
        ]);
    }

    ParameterValue::query()->create([
        'team_id' => $team->id,
        'monitored_system_id' => $monitoredSystem->id,
        'parameter_id' => $parameter->id,
        'measured_at' => '2026-06-30 10:00:00',
        'measured_date' => '2026-06-30',
        'value' => 1,
    ]);

    ParameterValue::query()->create([
        'team_id' => $team->id,
        'monitored_system_id' => $monitoredSystem->id,
        'parameter_id' => $parameter->id,
        'measured_at' => '2026-06-24 10:00:00',
        'measured_date' => '2026-06-24',
        'value' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('teams.index', ['search' => 'Unit']));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Index')
            ->where('filters.status', 'active')
            ->where('filters.search', 'Unit')
            ->where('filters.role', 'all')
            ->has('teams.data', 1)
            ->where('teams.data.0.name', 'Outside Unit')
            ->has('teams.data.0.parameterValueActivity', 7)
            ->where('teams.data.0.parameterValueActivity.4.date', '2026-06-30')
            ->where('teams.data.0.parameterValueActivity.4.count', 1)
            ->where('teams.data.0.parameterValueActivity.6.label', '02/07/2026')
            ->where('teams.data.0.parameterValueActivity.6.count', 3)
        );
});

test('non admin users cannot access the teams index page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this
        ->actingAs($user)
        ->get(route('teams.index'));

    $response->assertForbidden();
});

test('the teams index filters units by status search and role', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['name' => 'Rita Owner']);
    $admin = User::factory()->create(['name' => 'Aaron Admin']);
    $activeTeam = Team::factory()->create(['name' => 'Alpha Unit']);
    $inactiveTeam = Team::factory()->inactive()->create(['name' => 'Legacy Unit']);

    $activeTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $inactiveTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $inactiveTeam->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('teams.index', [
            'status' => 'inactive',
            'search' => 'Legacy',
            'role' => 'admin',
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Index')
            ->where('filters.status', 'inactive')
            ->where('filters.search', 'Legacy')
            ->where('filters.role', 'admin')
            ->has('teams.data', 1)
            ->where('teams.data.0.name', 'Legacy Unit')
            ->where('teams.data.0.isActive', false)
            ->where('teams.data.0.roleLabel', 'Rita Owner')
            ->where('teams.data.0.members.0.name', 'Aaron Admin')
        );
});

test('teams can be created', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('teams.store'), [
            'name' => 'Test Team',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'name' => 'Test Team',
        'is_personal' => false,
    ]);
});

test('team slug uses next available suffix', function () {
    $user = User::factory()->create();

    Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
    Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

    $this
        ->actingAs($user)
        ->post(route('teams.store'), [
            'name' => 'Acme',
        ]);

    $this->assertDatabaseHas('teams', [
        'name' => 'Acme',
        'slug' => 'acme-11',
    ]);
});

test('the team edit page can be rendered', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('teams.edit', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Edit')
            ->where('members.0.role', TeamRole::Owner->value)
            ->where('members.0.role_label', TeamRole::Owner->label()),
        );
});

test('admin users can render team edit pages without membership', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('teams.edit', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/Edit')
            ->where('permissions.canUpdateTeam', true)
            ->where('permissions.canDeleteTeam', true),
        );
});

test('teams can be updated by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Original Name']);

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => 'Updated Name',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Updated Name',
    ]);
});

test('teams cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->patch(route('teams.update', $team), [
            'name' => 'Updated Name',
        ]);

    $response->assertForbidden();
});

test('teams can be deleted by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);
});

test('team deletion requires name confirmation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $team), [
            'name' => 'Wrong Name',
        ]);

    $response->assertSessionHasErrors('name');

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'deleted_at' => null,
    ]);
});

test('deleting current team switches to alphabetically first remaining team', function () {
    $user = User::factory()->create(['name' => 'Mike']);

    $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
    $zuluTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $zuluTeam->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $zuluTeam), [
            'name' => $zuluTeam->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('teams', [
        'id' => $zuluTeam->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($alphaTeam->id);
});

test('deleting current team falls back to personal team when alphabetically first', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();
    $team = Team::factory()->create(['name' => 'Zulu Team']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $team->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($personalTeam->id);
});

test('deleting non current team leaves current team unchanged', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $personalTeam->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($personalTeam->id);
});

test('members can leave non personal teams', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->delete(route('teams.leave', $team));

    $response->assertRedirect(route('teams.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => "You left the team \"{$team->name}\""]);

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('leaving current team switches to alphabetically first remaining team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Mike']);

    $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
    $zuluTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $zuluTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $zuluTeam->id]);

    $response = $this
        ->actingAs($member)
        ->delete(route('teams.leave', $zuluTeam));

    $response->assertRedirect(route('teams.index'));

    expect($member->fresh()->belongsToTeam($zuluTeam))->toBeFalse();
    expect($member->fresh()->current_team_id)->toEqual($alphaTeam->id);
});

test('personal teams cannot be left', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.leave', $personalTeam));

    $response->assertForbidden();

    expect($user->fresh()->belongsToTeam($personalTeam))->toBeTrue();
});

test('team owners cannot leave their team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.leave', $team));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('users cannot leave teams they dont belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.leave', $team));

    $response->assertForbidden();
});

test('deleting team switches other affected users to their personal team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $owner->update(['current_team_id' => $team->id]);
    $member->update(['current_team_id' => $team->id]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertRedirect();

    expect($member->fresh()->current_team_id)->toEqual($member->personalTeam()->id);
});

test('personal teams cannot be deleted', function () {
    $user = User::factory()->create();

    $personalTeam = $user->personalTeam();

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $personalTeam), [
            'name' => $personalTeam->name,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('teams', [
        'id' => $personalTeam->id,
        'deleted_at' => null,
    ]);
});

test('teams cannot be deleted by non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertForbidden();
});

test('users can switch teams', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($user)
        ->post(route('teams.switch', $team));

    $response->assertRedirect();

    expect($user->fresh()->current_team_id)->toEqual($team->id);
});

test('users cannot switch to team they dont belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('teams.switch', $team));

    $response->assertForbidden();
});

test('guests cannot access teams', function () {
    $response = $this->get(route('teams.index'));

    $response->assertRedirect(route('login'));
});

test('team active status can be updated', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['is_active' => true]);

    $response = $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'is_active' => false,
        ]);

    $response->assertRedirect();

    expect($team->fresh()->is_active)->toBeFalse();
});

test('toUserTeams excludes inactive teams by default', function () {
    $user = User::factory()->create();
    $activeTeam = Team::factory()->create(['is_active' => true]);
    $inactiveTeam = Team::factory()->create(['is_active' => false]);

    $activeTeam->members()->attach($user, ['role' => TeamRole::Member->value]);
    $inactiveTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $userTeams = $user->toUserTeams(includeCurrent: true);

    expect($userTeams->pluck('id')->all())->toContain($activeTeam->id)
        ->and($userTeams->pluck('id')->all())->not->toContain($inactiveTeam->id);
});

test('admin toUserTeams returns all active teams and excludes inactive teams across the system', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $activeTeam1 = Team::factory()->create(['is_active' => true]);
    $activeTeam2 = Team::factory()->create(['is_active' => true]);
    $inactiveTeam = Team::factory()->create(['is_active' => false]);

    $adminTeams = $admin->toUserTeams(includeCurrent: true);
    $ids = $adminTeams->pluck('id')->all();

    expect($ids)->toContain($activeTeam1->id)
        ->and($ids)->toContain($activeTeam2->id)
        ->and($ids)->not->toContain($inactiveTeam->id);
});

test('admin can switch to any active team without explicit membership', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['is_active' => true]);

    expect($admin->belongsToTeam($team))->toBeFalse();

    $response = $this
        ->actingAs($admin)
        ->post(route('teams.switch', $team));

    $response->assertRedirect();
    expect($admin->fresh()->current_team_id)->toBe($team->id);
});

test('admin can access dashboard of any active team without explicit membership', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['is_active' => true]);

    $response = $this
        ->actingAs($admin)
        ->get("/{$team->slug}/dashboard");

    $response->assertOk();
});
