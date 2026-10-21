<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    $this->user->update(['current_team_id' => $this->team->id]);
    $this->system = MonitoredSystem::query()->create(['team_id' => $this->team->id, 'name' => 'Sistema principal', 'sort_order' => 1, 'is_active' => true]);
    MonitoredSystem::query()->create(['team_id' => $this->team->id, 'name' => 'Sistema seguinte', 'sort_order' => 2, 'is_active' => true]);
    $this->parameter = Parameter::query()->create(['team_id' => $this->team->id, 'monitored_system_id' => $this->system->id, 'name' => 'pH', 'tag' => 'PH-001', 'sort_order' => 1, 'is_active' => true]);
    ParameterValue::query()->create(['team_id' => $this->team->id, 'monitored_system_id' => $this->system->id, 'parameter_id' => $this->parameter->id, 'measured_at' => now()->startOfHour(), 'measured_date' => today(), 'value' => 7.5]);
    $this->actingAs($this->user);
});

test('table charts are loaded only when requested and support lines and bars', function () {
    visit(route('data-table.index', $this->team))
        ->assertDontSee('Criar relatório')
        ->assertDontSee('Editar considerações Consucal')
        ->click('Carregar')
        ->assertSee('PH-001')
        ->screenshot(filename: 'data-table-enhancements')
        ->assertMissing('[data-report-chart-renderer]')
        ->click('[aria-label="Gráfico de linha de pH"]')
        ->assertSee('Leituras do período carregado')
        ->assertPresent('[data-report-chart-renderer]')
        ->click('Barras')
        ->screenshot(filename: 'data-table-chart')
        ->assertNoJavaScriptErrors();
});

test('table comments can be saved and their changes appear in history', function () {
    visit(route('data-table.index', $this->team))
        ->click('Carregar')
        ->assertSee('PH-001')
        ->click('[title="Comentários"]')
        ->screenshot(filename: 'data-table-comments')
        ->fill('[id^="row-comment-"]', 'Coleta conferida')
        ->click('Salvar comentário')
        ->assertSee('Comentário salvo')
        ->click('Histórico')
        ->assertSee('Coleta conferida')
        ->assertNoJavaScriptErrors();
});

test('table system selection navigation and options work independently', function () {
    visit(route('data-table.index', $this->team))
        ->click('Carregar')
        ->assertSee('PH-001')
        ->click('[aria-label="Selecionar sistemas"]')
        ->click('[aria-label="Selecionar Sistema seguinte"]')
        ->assertSee('Todos os sistemas (2)')
        ->click('button:has-text("Sistema seguinte")')
        ->assertMissing('input[aria-label="Selecionar Sistema principal"]')
        ->click('[title="Sistema anterior"]')
        ->assertSee('PH-001')
        ->click('[title="Próximo sistema"]')
        ->click('[title="Sistema anterior"]')
        ->assertSee('PH-001')
        ->click('[title="Opções da tabela"]')
        ->assertSee('Calcular EWMA em todos os resultados')
        ->click('[role="menuitemcheckbox"]:has-text("Calcular EWMA em todos os resultados")')
        ->assertAttribute('[role="menuitemcheckbox"]:has-text("Calcular EWMA em todos os resultados")', 'aria-checked', 'true')
        ->assertNoJavaScriptErrors();
});

test('data entry shows a clickable EWMA calculation while typing', function () {
    $parameter = Parameter::query()->create(['team_id' => $this->team->id, 'monitored_system_id' => $this->system->id, 'name' => 'Temperatura', 'tag' => 'TT-001', 'is_active' => true]);
    for ($index = 1; $index <= 25; $index++) {
        $at = now()->startOfMinute()->subHours($index);
        ParameterValue::query()->create(['team_id' => $this->team->id, 'monitored_system_id' => $this->system->id, 'parameter_id' => $parameter->id, 'measured_at' => $at, 'measured_date' => $at->toDateString(), 'value' => 10 + $index % 2]);
    }

    visit(route('data-entry.index', $this->team))
        ->assertSee('TT-001')
        ->fill('[aria-label="Resultado de Temperatura"]', '100')
        ->click('[aria-label="Ver cálculo EWMA de Temperatura"]')
        ->assertSee('acima do limite superior')
        ->assertSee('20 leituras anteriores')
        ->assertNoJavaScriptErrors();
});
