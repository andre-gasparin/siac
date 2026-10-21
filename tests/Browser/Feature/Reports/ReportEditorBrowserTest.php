<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;

test('administrator opens a new report editor without javascript errors', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => 'admin']);
    $admin->update(['current_team_id' => $team->id]);
    $system = MonitoredSystem::create([
        'team_id' => $team->id,
        'name' => 'Água clarificada',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $system->id,
        'name' => 'pH',
        'unit' => 'pH',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    visit(route('reports.index', $team))
        ->click('Novo relatório')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Finalizar relatório')
        ->assertSee('Água clarificada')
        ->click('[aria-label="Habilitar ou desabilitar sistema"]')
        ->click('Editar consideração')
        ->assertSee('Relatório em rascunho')
        ->click('Relatórios anteriores')
        ->assertSee('Copie um documento anterior para o editor')
        ->click('[data-test="close-history"]')
        ->click('Novo gráfico')
        ->assertSee('Monte as séries e acompanhe a prévia ao vivo')
        ->click('[data-test="chart-parameter-option"]')
        ->click('[data-test="insert-chart"]')
        ->assertSee('Gráfico do relatório')
        ->click('[data-test="edit-report-chart"]')
        ->assertSee('Editar gráfico')
        ->clear('[data-test="chart-title"]')
        ->type('[data-test="chart-title"]', 'Gráfico revisado')
        ->click('[data-test="insert-chart"]')
        ->assertSee('Gráfico revisado')
        ->click('Agente')
        ->assertSee('Posso investigar os dados desta empresa')
        ->assertSee('Faça o comentário do relatório')
        ->click('[aria-label="Ver funções do agente"]')
        ->assertSee('Funções do agente')
        ->click('[data-test="report-agent"] [title="Minimizar"]')
        ->click('[data-test="restore-report-agent"]')
        ->assertSee('Agente de relatórios')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('administrator creates a report directly from the reports page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => 'admin']);
    $admin->update(['current_team_id' => $team->id]);
    MonitoredSystem::query()->create(['team_id' => $team->id, 'name' => 'Sistema do relatório', 'sort_order' => 1, 'is_active' => true]);
    $this->actingAs($admin);

    visit(route('reports.index', $team))
        ->click('Novo relatório')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertPresent('[data-test="finalize-report"]')
        ->assertSee('Sistema do relatório')
        ->screenshot(filename: 'report-created-from-index')
        ->assertNoJavaScriptErrors();

    expect(Report::query()->count())->toBe(1);
});
