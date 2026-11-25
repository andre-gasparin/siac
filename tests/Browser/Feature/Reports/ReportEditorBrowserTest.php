<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

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
        ->click('Água clarificada')
        ->assertSee('Considerações Consucal')
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
        ->click('[data-test="toggle-latest-comment"]')
        ->assertSee('Último comentário salvo')
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

test('report show page supports card header status borders, sidebar dots, report-date data count, floating toggle, pink defaults, and table visibility slider', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => 'admin']);
    $admin->update(['current_team_id' => $team->id]);

    $system = MonitoredSystem::create([
        'team_id' => $team->id,
        'name' => 'Sistema Teste',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $system->id,
        'name' => 'Turbidez',
        'unit' => 'NTU',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach (range(2, 22) as $index) {
        Parameter::create([
            'team_id' => $team->id,
            'monitored_system_id' => $system->id,
            'name' => "Parâmetro Extra {$index}",
            'unit' => 'mg/L',
            'decimals' => 2,
            'sort_order' => $index,
            'is_active' => true,
        ]);
    }

    ParameterValue::create([
        'team_id' => $team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => '2026-08-04 08:00:00',
        'measured_date' => '2026-08-04',
        'value' => 3.45,
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
        ->assertSee('Sistema Teste')
        ->assertNotPresent('[data-test="system-status-indicator"]')
        ->assertPresent("[data-nav-system-id='{$system->id}'] .rounded-full")
        ->assertSeeIn('[data-test="system-data-count"]', '1 dado')
        ->assertNotPresent('[data-test="system-block-actions"]')
        ->assertNotPresent('[data-test="toggle-hide-data-slider"]');

    // Initial state: red left border + red "Pendente" badge in systems combo
    expect($page->attribute("[data-report-system-id='{$system->id}'] > header", 'class'))->toContain('border-l-red-500');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertPresent('[data-test="systems-popover"]')
        ->assertSeeIn("[data-test='systems-popover-status-{$system->id}']", 'Pendente');

    expect($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'data-status'))->toBe('disabled')
        ->and($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'class'))->toContain('bg-red-500/15');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertNotPresent('[data-test="systems-popover"]');

    // Opening card header enables and opens editor, yellow left border + block actions visible + amber "Em avaliação" badge in systems combo
    $page->click("[data-report-system-id='{$system->id}'] > header")
        ->assertPresent('[data-test="save-report"]')
        ->assertPresent('[data-test="system-block-actions"]')
        ->assertAttribute('[data-test="toggle-hide-data-slider"]', 'aria-checked', 'true')
        ->assertSee('Flutuar')
        ->assertNotPresent('[data-test="floating-editor-header"]');

    expect($page->attribute("[data-report-system-id='{$system->id}'] > header", 'class'))->toContain('border-l-amber-500');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertPresent('[data-test="systems-popover"]')
        ->assertPresent('[data-test="systems-popover-editing-count"]')
        ->assertSeeIn("[data-test='systems-popover-status-{$system->id}']", 'Em avaliação');

    expect($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'data-status'))->toBe('editing')
        ->and($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'class'))->toContain('bg-amber-500/15');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertNotPresent('[data-test="systems-popover"]');

    // Toggle floating ON and back to fixed
    $page->click('[data-test="toggle-floating-editor"]')
        ->assertPresent('[data-test="floating-editor-header"]')
        ->assertSee('Fixar aqui')
        ->click('[data-test="toggle-floating-editor"]')
        ->assertNotPresent('[data-test="floating-editor-header"]');

    // Pink default indicators on font and highlight buttons
    $defaultSwatches = $page->script(<<<'JS'
        () => ({
            text: document.querySelector('[data-test="apply-text-color"] span:last-child')?.style.backgroundColor,
            highlight: document.querySelector('[data-test="apply-highlight-color"] span:last-child')?.style.backgroundColor,
        })
    JS);

    expect($defaultSwatches['text'])->toBe('rgb(236, 72, 153)')
        ->and($defaultSwatches['highlight'])->toBe('rgb(254, 205, 211)');

    // Type and apply pink formatting, then save -> green left border
    $page->click('[data-test="report-editor-content"]')
        ->type('[data-test="report-editor-content"]', 'Texto rosa')
        ->click('[data-test="apply-text-color"]')
        ->click('[data-test="save-report"]')
        ->assertSee('Texto rosa')
        ->assertPresent('[data-test="system-block-actions"]');

    expect($page->attribute("[data-report-system-id='{$system->id}'] > header", 'class'))->toContain('border-l-emerald-500');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertPresent('[data-test="systems-popover"]')
        ->assertNotPresent('[data-test="systems-popover-editing-count"]')
        ->assertSeeIn("[data-test='systems-popover-status-{$system->id}']", 'Concluído');

    expect($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'data-status'))->toBe('enabled')
        ->and($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'class'))->toContain('bg-emerald-500/15');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertNotPresent('[data-test="systems-popover"]');

    // Toggle slider OFF (hide in final user report) -> shows loading spinner while saving and keeps table visible on admin screen
    $page->script(<<<'JS'
        () => {
            const originalFetch = window.fetch.bind(window);
            window.fetch = async (input, init) => {
                const url = typeof input === 'string' ? input : input.url;
                if (url.includes('/sistemas') && init?.method === 'PUT') {
                    await new Promise((resolve) => setTimeout(resolve, 250));
                }
                return originalFetch(input, init);
            };
            window.__restoreFetch = () => {
                window.fetch = originalFetch;
            };
        }
    JS);

    $page->click('[data-test="toggle-hide-data-slider"]')
        ->assertPresent('[data-test="hide-data-loading"]')
        ->assertAttribute('[data-test="toggle-hide-data-slider"]', 'aria-busy', 'true')
        ->assertAttribute('[data-test="toggle-hide-data-slider"]', 'aria-checked', 'false')
        ->assertNotPresent('[data-test="hide-data-loading"]')
        ->click('Tabela de Dados (Histórico)')
        ->assertSee('Turbidez');

    $page->script('() => window.__restoreFetch?.()');

    $layoutMetrics = $page->script(<<<JS
        () => {
            const section = document.querySelector('[data-report-system-id="{$system->id}"]');
            const scrollBox = document.querySelector('[data-test="report-data-table-scroll"]');

            return {
                sectionWidth: section?.getBoundingClientRect().width ?? 0,
                docWidth: document.documentElement.clientWidth,
                tableScrollWidth: scrollBox?.scrollWidth ?? 0,
                tableClientWidth: scrollBox?.clientWidth ?? 0,
            };
        }
    JS);

    expect($layoutMetrics['sectionWidth'])->toBeLessThanOrEqual($layoutMetrics['docWidth'])
        ->and($layoutMetrics['tableScrollWidth'])->toBeGreaterThan($layoutMetrics['tableClientWidth'])
        ->and($page)->not->toBeNull();

    $page->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('report show supports inline grouping that brings target system in, stacked grouped tables, and finalize confirmation modal', function () {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => 'admin']);
    $admin->update(['current_team_id' => $team->id]);

    $systemA = MonitoredSystem::create([
        'team_id' => $team->id,
        'name' => 'Água Clarificada',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $systemB = MonitoredSystem::create([
        'team_id' => $team->id,
        'name' => 'Entrada dos Filtros',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $paramA = Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemA->id,
        'name' => 'pH Clarificada',
        'unit' => 'pH',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $paramB = Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemB->id,
        'name' => 'Turbidez Filtros',
        'unit' => 'NTU',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    ParameterValue::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemA->id,
        'parameter_id' => $paramA->id,
        'measured_at' => '2026-08-04 08:00:00',
        'measured_date' => '2026-08-04',
        'value' => 7.15,
        'source_type' => 'manual',
    ]);
    ParameterValue::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemB->id,
        'parameter_id' => $paramB->id,
        'measured_at' => '2026-08-04 08:00:00',
        'measured_date' => '2026-08-04',
        'value' => 1.25,
        'source_type' => 'manual',
    ]);

    $report = Report::create([
        'team_id' => $team->id,
        'created_by' => $admin->id,
        'title' => 'Relatório 04/08/2026',
        'date_reference' => '2026-08-04',
        'status' => 'draft',
    ]);

    ReportItem::create([
        'team_id' => $team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $systemA->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => '<p>Texto salvo A</p>',
    ]);
    ReportItem::create([
        'team_id' => $team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $systemB->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => '<p>Texto salvo B</p>',
    ]);

    $this->actingAs($admin);

    $page = visit(route('reports.show', [$team, $report]))
        ->assertSee('Água Clarificada')
        ->assertSee('Entrada dos Filtros');

    // Open inline grouping inside System A and bring System B into System A
    $page->click("[data-report-system-id='{$systemA->id}'] [data-test='toggle-inline-group']")
        ->assertPresent('[data-test="inline-group-picker"]')
        ->select('[data-test="inline-group-target-select"]', (string) $systemB->id)
        ->click('[data-test="confirm-inline-group"]')
        ->assertNotPresent('[data-test="inline-group-picker"]')
        ->assertSee('Sistemas agrupados · 2 sistemas')
        ->assertSee('Texto salvo A')
        ->assertSee('Texto salvo B');

    // Open historical data table and verify two stacked tables without grouped toggle filters
    $page->click("[data-report-system-id='{$systemA->id}'] button:has-text('Tabela de Dados (Histórico)')")
        ->assertSee('pH Clarificada')
        ->assertSee('Turbidez Filtros');

    $sectionCount = $page->script("() => document.querySelectorAll('[data-test=\"history-system-section\"]').length");
    expect($sectionCount)->toBe(2);

    // Finalize confirmation popup
    $page->click('[data-test="finalize-report"]')
        ->assertPresent('[data-test="finalize-confirm-dialog"]')
        ->click('[data-test="cancel-finalize-report"]')
        ->assertNotPresent('[data-test="finalize-confirm-dialog"]')
        ->click('[data-test="finalize-report"]')
        ->assertPresent('[data-test="finalize-confirm-dialog"]')
        ->click('[data-test="confirm-finalize-report"]')
        ->assertPresent('[data-test="resend-report"]')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
