<?php

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

test('user can view data table page for current team', function () {
    $response = $this->actingAs($this->user)
        ->get(route('data-table.index', ['current_team' => $this->team->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('DataTable/Index')
            ->has('systems')
            ->has('defaultStartDate')
            ->has('defaultEndDate')
        );
});

test('data table data endpoint returns parameters ordered by sort_order for single system', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Água Clarificada',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $paramA = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Sílica',
        'unit' => 'mg/L',
        'decimals' => 2,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $paramB = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'pH',
        'unit' => 'pH',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $paramB->id,
        'measured_at' => Carbon::now()->subDays(1),
        'measured_date' => Carbon::now()->subDays(1)->toDateString(),
        'value' => 7.45,
        'source_type' => 'manual',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('data-table.data', [
            'current_team' => $this->team->slug,
            'system_ids' => [$system->id],
        ]));

    $response->assertOk()
        ->assertJson([
            'is_multi_system' => false,
        ]);

    $data = $response->json();
    $this->assertCount(2, $data['parameters']);
    // For single system, sort_order 1 (pH) comes before sort_order 2 (Sílica)
    $this->assertEquals($paramB->id, $data['parameters'][0]['id']);
    $this->assertEquals($paramA->id, $data['parameters'][1]['id']);
});

test('data table data endpoint returns the established empty payload without selected systems', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('data-table.data', [
            'current_team' => $this->team->slug,
        ]));

    $response->assertOk()
        ->assertExactJson([
            'parameters' => [],
            'rows' => [],
            'averages' => [],
            'is_multi_system' => false,
        ]);
});

test('data table data endpoint orders parameters by character approximation across multiple systems', function () {
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

    // System 1 parameters
    $sys1ParamSilica = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'name' => 'Sílica',
        'unit' => 'mg/L',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $sys1ParamPh = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'name' => 'pH @ 25°C',
        'unit' => 'pH',
        'decimals' => 2,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // System 2 parameters
    $sys2ParamCondutividade = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'name' => 'Condutividade',
        'unit' => 'uS/cm',
        'decimals' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $sys2ParamPh = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'name' => 'pH @ 25°C',
        'unit' => 'pH',
        'decimals' => 2,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('data-table.data', [
            'current_team' => $this->team->slug,
            'system_ids' => [$system1->id, $system2->id],
        ]));

    $response->assertOk()
        ->assertJson([
            'is_multi_system' => true,
        ]);

    $data = $response->json();
    $this->assertCount(4, $data['parameters']);

    // Ordered naturally by name: "Condutividade", "pH @ 25°C" (Sys1), "pH @ 25°C" (Sys2), "Sílica"
    $paramNames = array_column($data['parameters'], 'name');
    $this->assertEquals(['Condutividade', 'pH @ 25°C', 'pH @ 25°C', 'Sílica'], $paramNames);
});

test('data table calculates column averages correctly', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Teste Média',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Vazão',
        'unit' => 'm3/h',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => Carbon::now()->subDays(2),
        'measured_date' => Carbon::now()->subDays(2)->toDateString(),
        'value' => 10.0,
        'source_type' => 'manual',
    ]);

    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => Carbon::now()->subDays(1),
        'measured_date' => Carbon::now()->subDays(1)->toDateString(),
        'value' => 20.0,
        'source_type' => 'manual',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('data-table.data', [
            'current_team' => $this->team->slug,
            'system_ids' => [$system->id],
        ]));

    $response->assertOk();
    $data = $response->json();

    $this->assertArrayHasKey($parameter->id, $data['averages']);
    $this->assertEquals(15.0, $data['averages'][$parameter->id]['numeric']);
    $this->assertEquals('15,00', $data['averages'][$parameter->id]['formatted']);
});

test('user can delete a row of parameter values by timestamp', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Exclusão',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Pressão',
        'unit' => 'bar',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $timestamp = Carbon::now()->subDays(1)->startOfHour();

    $pv = ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => $timestamp,
        'measured_date' => $timestamp->toDateString(),
        'value' => 5.5,
        'source_type' => 'manual',
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('data-table.rows.destroy', ['current_team' => $this->team->slug]), [
            'timestamp' => $timestamp->toDateTimeString(),
            'parameter_ids' => [$parameter->id],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'deleted_count' => 1,
        ]);

    $this->assertDatabaseMissing('parameter_values', [
        'id' => $pv->id,
    ]);
});

test('data table row deletion preserves the invalid payload response contract', function () {
    $this->actingAs($this->user)
        ->deleteJson(route('data-table.rows.destroy', ['current_team' => $this->team->slug]))
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Dados inválidos para exclusão.',
        ]);
});

test('user can update an existing parameter value via cell update endpoint', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Edição',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Temperatura',
        'unit' => '°C',
        'decimals' => 1,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $timestamp = Carbon::now()->subDays(1)->startOfHour();

    $pv = ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => $timestamp,
        'measured_date' => $timestamp->toDateString(),
        'value' => 25.0,
        'source_type' => 'manual',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('data-table.cell.update', ['current_team' => $this->team->slug]), [
            'parameter_id' => $parameter->id,
            'timestamp' => $timestamp->toDateTimeString(),
            'value' => 28.5,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'parameter_id' => $parameter->id,
            'value' => 28.5,
        ]);

    $this->assertDatabaseHas('parameter_values', [
        'id' => $pv->id,
        'value' => 28.5,
    ]);
});

test('user can clear a parameter value by setting null', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Edição Null',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Cloro',
        'unit' => 'ppm',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $timestamp = Carbon::now()->subDays(1)->startOfHour();

    $pv = ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => $timestamp,
        'measured_date' => $timestamp->toDateString(),
        'value' => 1.2,
        'source_type' => 'manual',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('data-table.cell.update', ['current_team' => $this->team->slug]), [
            'parameter_id' => $parameter->id,
            'timestamp' => $timestamp->toDateTimeString(),
            'value' => null,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'value' => null,
        ]);

    $this->assertDatabaseMissing('parameter_values', [
        'id' => $pv->id,
    ]);
});

test('user can create a parameter value for an empty cell', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema Nova Leitura',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Turbidez',
        'unit' => 'NTU',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $timestamp = Carbon::now()->subDays(1)->startOfHour();

    $response = $this->actingAs($this->user)
        ->putJson(route('data-table.cell.update', ['current_team' => $this->team->slug]), [
            'parameter_id' => $parameter->id,
            'timestamp' => $timestamp->toDateTimeString(),
            'value' => 0.45,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'value' => 0.45,
        ]);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'parameter_id' => $parameter->id,
        'value' => 0.45,
    ]);
});

test('user can update row timestamp across all parameters and systems', function () {
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
        'name' => 'Param A',
        'unit' => 'uA',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $param2 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'name' => 'Param B',
        'unit' => 'uB',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $oldTimestamp = Carbon::now()->subDays(2)->startOfHour();
    $newTimestamp = Carbon::now()->subDays(1)->startOfHour();

    $pv1 = ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system1->id,
        'parameter_id' => $param1->id,
        'measured_at' => $oldTimestamp,
        'measured_date' => $oldTimestamp->toDateString(),
        'value' => 10.0,
        'source_type' => 'manual',
    ]);

    $pv2 = ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system2->id,
        'parameter_id' => $param2->id,
        'measured_at' => $oldTimestamp,
        'measured_date' => $oldTimestamp->toDateString(),
        'value' => 20.0,
        'source_type' => 'manual',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('data-table.rows.timestamp.update', ['current_team' => $this->team->slug]), [
            'old_timestamp' => $oldTimestamp->toDateTimeString(),
            'new_timestamp' => $newTimestamp->toDateTimeString(),
            'parameter_ids' => [$param1->id, $param2->id],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'updated_count' => 2,
            'date' => $newTimestamp->format('d/m/Y'),
            'time' => $newTimestamp->format('H:i'),
        ]);

    $this->assertDatabaseHas('parameter_values', [
        'id' => $pv1->id,
        'measured_at' => $newTimestamp->toDateTimeString(),
        'measured_date' => $newTimestamp->toDateString(),
    ]);

    $this->assertDatabaseHas('parameter_values', [
        'id' => $pv2->id,
        'measured_at' => $newTimestamp->toDateTimeString(),
        'measured_date' => $newTimestamp->toDateString(),
    ]);
});
