<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;

test('report chart builder supports favorites toggle, ordered parameters, right-side title, no height slider, and renders saved charts without blocking scroll', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => 'admin']);
    $admin->update(['current_team_id' => $team->id]);

    $systemTwo = MonitoredSystem::create([
        'team_id' => $team->id,
        'name' => 'Sistema Dois',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $systemOne = MonitoredSystem::create([
        'team_id' => $team->id,
        'name' => 'Sistema Um',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $paramSecond = Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemOne->id,
        'name' => 'Turbidez',
        'unit' => 'NTU',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $paramFirst = Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemOne->id,
        'name' => 'pH',
        'unit' => 'pH',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemTwo->id,
        'name' => 'Cloro',
        'unit' => 'mg/L',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    ParameterValue::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemOne->id,
        'parameter_id' => $paramFirst->id,
        'measured_at' => '2026-08-04 08:00:00',
        'measured_date' => '2026-08-04',
        'value' => 7.15,
        'source_type' => 'manual',
    ]);

    $report = Report::create([
        'team_id' => $team->id,
        'created_by' => $admin->id,
        'title' => 'Relatório 04/08/2026',
        'date_reference' => '2026-08-04',
        'status' => 'draft',
    ]);

    $this->actingAs($admin);

    $page = visit(route('reports.show', [$team, $report]))
        ->assertSee('Sistema Um')
        ->click('Sistema Um')
        ->assertPresent('[data-test="save-report"]')
        ->click('Novo gráfico')
        ->assertSee('Monte as séries e acompanhe a prévia ao vivo')
        ->assertAttribute('[data-test="favorites-toggle"]', 'aria-checked', 'false')
        ->assertNotPresent('input[type="range"][min="220"]')
        ->assertPresent('[data-test="chart-title"]')
        ->assertPresent('[data-test="chart-parameter-option"]');

    $orderedOptions = $page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('[data-test="chart-parameter-option"]'))
            .map((el) => el.textContent.replace(/\s+/g, ' ').trim())
    JS);

    expect($orderedOptions[0])->toContain('pH')
        ->and($orderedOptions[1])->toContain('Turbidez')
        ->and($orderedOptions[2])->toContain('Cloro');

    $page->click('[data-test="favorites-toggle"]')
        ->assertAttribute('[data-test="favorites-toggle"]', 'aria-checked', 'true')
        ->assertPresent('[data-test="favorite-charts-dropdown"]')
        ->assertNotPresent('[data-test="chart-parameter-option"]')
        ->assertPresent('[data-test="toggle-system-parameters"]')
        ->click('[data-test="toggle-system-parameters"]')
        ->assertPresent('[data-test="chart-parameter-option"]')
        ->click('[data-test="favorites-toggle"]')
        ->assertAttribute('[data-test="favorites-toggle"]', 'aria-checked', 'false')
        ->assertPresent('[data-test="chart-parameter-option"]')
        ->click('[data-test="chart-parameter-option"]:first-of-type')
        ->clear('[data-test="chart-title"]')
        ->type('[data-test="chart-title"]', 'Gráfico Teste Salvo')
        ->click('[data-test="favorites-toggle"]')
        ->click('[data-test="save-favorite-btn"]')
        ->assertPresent('[data-test="save-as-new-favorite-btn"]')
        ->assertPresent('[data-test="delete-favorite-btn"]')
        ->click('[data-test="insert-chart"]')
        ->assertSee('Gráfico Teste Salvo')
        ->click('[data-test="save-report"]')
        ->assertNotPresent('[data-test="save-report"]')
        ->assertPresent('[data-report-chart-node]')
        ->assertPresent('[data-report-chart-renderer] canvas')
        ->assertSee('Gráfico Teste Salvo')
        ->assertNoJavaScriptErrors();

    $scrollCheck = $page->script(<<<'JS'
        () => {
            const chart = document.querySelector('[data-report-chart-renderer]');
            if (!chart) return { found: false };
            const rect = chart.getBoundingClientRect();
            const wheelEvent = new WheelEvent('wheel', {
                deltaY: 120,
                bubbles: true,
                cancelable: true,
                clientX: rect.left + rect.width / 2,
                clientY: rect.top + rect.height / 2,
            });
            const notCancelled = chart.dispatchEvent(wheelEvent);
            return { found: true, notCancelled };
        }
    JS);

    expect($scrollCheck['found'])->toBeTrue()
        ->and($scrollCheck['notCancelled'])->toBeTrue();
});
