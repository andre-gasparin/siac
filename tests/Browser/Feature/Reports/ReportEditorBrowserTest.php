<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
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

test('report show page supports disable confirmation tooltip, status border colors, floating toggle, pink defaults, and table visibility slider', function () {
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
        ->assertNotPresent('[data-test="system-block-actions"]')
        ->assertNotPresent('[data-test="toggle-hide-data-slider"]');

    // Initial state: red bolinha + red border + red "Desabilitado" badge in systems combo
    expect($page->attribute("[data-report-system-id='{$system->id}']", 'class'))->toContain('border-red-500/60')
        ->and($page->attribute('[data-test="system-status-indicator"]', 'class'))->toContain('bg-red-500');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertPresent('[data-test="systems-popover"]')
        ->assertSeeIn("[data-test='systems-popover-status-{$system->id}']", 'Desabilitado');

    expect($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'data-status'))->toBe('disabled')
        ->and($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'class'))->toContain('bg-red-500/15');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertNotPresent('[data-test="systems-popover"]');

    // Enabling via bolinha opens editor directly without confirmation, fixed by default, yellow border + block actions visible + amber "Em edição" badge in systems combo
    $page->click('[data-test="system-status-indicator"]')
        ->assertPresent('[data-test="save-report"]')
        ->assertPresent('[data-test="system-block-actions"]')
        ->assertAttribute('[data-test="toggle-hide-data-slider"]', 'aria-checked', 'true')
        ->assertNotPresent('[data-test="disable-system-confirmation"]')
        ->assertSee('Flutuar')
        ->assertNotPresent('[data-test="floating-editor-header"]');

    expect($page->attribute("[data-report-system-id='{$system->id}']", 'class'))->toContain('border-amber-500/60')
        ->and($page->attribute('[data-test="system-status-indicator"]', 'class'))->toContain('bg-amber-500');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertPresent('[data-test="systems-popover"]')
        ->assertPresent('[data-test="systems-popover-editing-count"]')
        ->assertSeeIn("[data-test='systems-popover-status-{$system->id}']", 'Em edição');

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

    // Type and apply pink formatting, then save -> green bolinha + green border
    $page->click('[data-test="report-editor-content"]')
        ->type('[data-test="report-editor-content"]', 'Texto rosa')
        ->click('[data-test="apply-text-color"]')
        ->click('[data-test="save-report"]')
        ->assertSee('Texto rosa')
        ->assertPresent('[data-test="system-block-actions"]');

    expect($page->attribute("[data-report-system-id='{$system->id}']", 'class'))->toContain('border-emerald-500/60')
        ->and($page->attribute('[data-test="system-status-indicator"]', 'class'))->toContain('bg-emerald-500');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertPresent('[data-test="systems-popover"]')
        ->assertNotPresent('[data-test="systems-popover-editing-count"]')
        ->assertSeeIn("[data-test='systems-popover-status-{$system->id}']", 'Habilitado');

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
        ->click('Exibir tabela de dados')
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
        ->and($layoutMetrics['tableScrollWidth'])->toBeGreaterThan($layoutMetrics['tableClientWidth']);

    // Clicking bolinha to disable shows confirmation tooltip; cancel keeps enabled, confirm disables
    $page->click('[data-test="system-status-indicator"]')
        ->assertPresent('[data-test="disable-system-confirmation"]')
        ->assertSee('Deseja realmente desabilitar este sistema?')
        ->click('[data-test="cancel-disable-system"]')
        ->assertNotPresent('[data-test="disable-system-confirmation"]');

    expect($page->attribute('[data-test="system-status-indicator"]', 'class'))->toContain('bg-emerald-500');

    $page->click('[data-test="system-status-indicator"]')
        ->assertPresent('[data-test="disable-system-confirmation"]')
        ->click('[data-test="confirm-disable-system"]')
        ->assertNotPresent('[data-test="disable-system-confirmation"]')
        ->assertNotPresent('[data-test="system-block-actions"]')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    expect($page->attribute("[data-report-system-id='{$system->id}']", 'class'))->toContain('border-red-500/60')
        ->and($page->attribute('[data-test="system-status-indicator"]', 'class'))->toContain('bg-red-500');

    $page->click('[data-test="systems-popover-trigger"]')
        ->assertPresent('[data-test="systems-popover"]')
        ->assertSeeIn("[data-test='systems-popover-status-{$system->id}']", 'Desabilitado');

    expect($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'data-status'))->toBe('disabled')
        ->and($page->attribute("[data-test='systems-popover-status-{$system->id}']", 'class'))->toContain('bg-red-500/15');
});
