<?php

use App\Models\CalculationRule;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['is_admin' => true, 'current_team_id' => $this->team->id]);
    $this->system = MonitoredSystem::create(['team_id' => $this->team->id, 'name' => 'Sistema teste', 'is_active' => true]);
    $this->parameters = collect(['Entrada principal', 'Entrada secundária', 'Resultado'])->map(fn (string $name): Parameter => Parameter::create([
        'team_id' => $this->team->id, 'monitored_system_id' => $this->system->id, 'name' => $name, 'is_active' => true,
    ]));
    $this->actingAs($this->user);
});

test('administrator creates edits and opens recalculation through the form', function () {
    $page = visit(route('calculations.index', $this->team));
    $page->click('Novo cálculo')->fill('#calculation-name', 'Ciclo do teste')
        ->select('#primary-system', (string) $this->system->id)
        ->select('#primary-parameter', (string) $this->parameters[0]->id)
        ->select('#secondary-system', (string) $this->system->id)
        ->select('#secondary-parameter', (string) $this->parameters[1]->id)
        ->select('#destination-system', (string) $this->system->id)
        ->select('#destination-parameter', (string) $this->parameters[2]->id)
        ->click('#primary-enabled')->fill('#primary-multiplier', '10')
        ->click('Salvar cálculo')->assertSee('Ciclo do teste')->assertNoJavaScriptErrors();
    expect(CalculationRule::sole()->primary_multiplier)->toBe(10.0);
    $page->click('Editar')->select('#calculation-type', 'percentage')->click('Salvar cálculo')
        ->assertSee('Relação percentual')->click('Recalcular')->assertSee('Data inicial')
        ->assertSee('Data final')->assertNoJavaScriptErrors();
});

test('administrator deletes a calculation after confirmation', function () {
    CalculationRule::create(['team_id' => $this->team->id, 'name' => 'Excluir teste', 'type' => 'cycle',
        'primary_parameter_id' => $this->parameters[0]->id, 'secondary_parameter_id' => $this->parameters[1]->id,
        'destination_parameter_id' => $this->parameters[2]->id]);
    visit(route('calculations.index', $this->team))->click('Excluir')->click('Excluir cálculo')
        ->assertSee('Nenhum cálculo cadastrado')->assertNoJavaScriptErrors();
    expect(CalculationRule::count())->toBe(0);
});

test('the calculation menu is hidden for members', function () {
    $this->user->update(['is_admin' => false]);
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    visit(route('dashboard', $this->team))->assertMissing('a[href$="/calculos"]')->assertNoJavaScriptErrors();
});
