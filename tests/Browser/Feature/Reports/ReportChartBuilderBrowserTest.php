<?php

use App\Models\ChartTemplate;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;

test('report chart builder opens with favorites enabled and visible, right-side save favorite, series options below chart, and selectable parameter limits', function () {
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
        'alert_1_min' => 0,
        'alert_1_max' => 5,
        'is_active' => true,
    ]);

    $paramFirst = Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemOne->id,
        'name' => 'pH',
        'unit' => 'pH',
        'sort_order' => 1,
        'alert_1_min' => 6.5,
        'alert_1_max' => 8.5,
        'is_active' => true,
    ]);

    $paramThird = Parameter::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemTwo->id,
        'name' => 'Cloro Residual Livre',
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

    ParameterValue::create([
        'team_id' => $team->id,
        'monitored_system_id' => $systemOne->id,
        'parameter_id' => $paramSecond->id,
        'measured_at' => '2026-08-04 08:00:00',
        'measured_date' => '2026-08-04',
        'value' => 1.8,
        'source_type' => 'manual',
    ]);

    $existingFavorite = ChartTemplate::create([
        'team_id' => $team->id,
        'user_id' => $admin->id,
        'name' => 'Água de Alimentação - Ox. Dis. x Vazão DEA x Temperatura Muito Longo Para Testar Quebra',
        'is_favorite' => true,
        'options' => ['height' => 320],
    ]);

    $existingFavorite->series()->createMany([
        [
            'team_id' => $team->id,
            'monitored_system_id' => $systemOne->id,
            'parameter_id' => $paramFirst->id,
            'axis' => 1,
            'sort_order' => 0,
            'label' => 'pH (ON)',
            'color' => null,
            'options' => ['chart_type' => 'line'],
        ],
        [
            'team_id' => $team->id,
            'monitored_system_id' => $systemOne->id,
            'parameter_id' => $paramSecond->id,
            'axis' => 2,
            'sort_order' => 1,
            'label' => 'Turbidez (ON)',
            'color' => null,
            'options' => ['chart_type' => 'line'],
        ],
        [
            'team_id' => $team->id,
            'monitored_system_id' => $systemTwo->id,
            'parameter_id' => $paramThird->id,
            'axis' => 1,
            'sort_order' => 2,
            'label' => 'Cloro Residual Livre',
            'color' => '#7C3AED',
            'options' => ['chart_type' => 'line'],
        ],
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
        ->assertDontSee('Pré-visualização')
        ->assertDontSee('Toda alteração é aplicada automaticamente')
        ->assertAttribute('[data-test="favorites-toggle"]', 'aria-checked', 'true')
        ->assertPresent('[data-test="favorites-list"]')
        ->assertSee('Água de Alimentação - Ox. Dis.')
        ->assertNotPresent('input[type="range"][min="220"]')
        ->assertPresent('[data-test="chart-title"]')
        ->assertPresent('[data-test="save-favorite-btn"]')
        ->assertNotPresent('[data-test="chart-parameter-option"]')
        ->assertPresent('[data-test="toggle-system-parameters"]')
        ->click('[data-test="favorite-option"]:first-of-type')
        ->assertCount('[data-test="series-summary-chip"]', 3);

    $favoriteChecks = $page->script(<<<'JS'
        () => {
            const modalSection = document.querySelector('[data-test="chart-builder"] section');
            const leftPane = modalSection?.firstElementChild;
            const chips = Array.from(document.querySelectorAll('[data-test="series-summary-chip"]'));
            const chipColors = chips.map((chip) => {
                const dot = chip.querySelector('span');
                return dot ? getComputedStyle(dot).backgroundColor : '';
            });
            const colorInputs = Array.from(document.querySelectorAll('[data-test="chart-series-item"] input[type="color"]'))
                .map((input) => input.value.toUpperCase());

            return {
                leftNoHorizontalOverflow: leftPane ? leftPane.scrollWidth <= leftPane.clientWidth + 1 : false,
                uniqueChipColors: new Set(chipColors).size,
                colorInputs,
            };
        }
    JS);

    expect($favoriteChecks['leftNoHorizontalOverflow'])->toBeTrue()
        ->and($favoriteChecks['uniqueChipColors'])->toBe(3)
        ->and($favoriteChecks['colorInputs'])->toBe(['#2563EB', '#E11D48', '#7C3AED']);

    $page->click('[data-test="toggle-system-parameters"]')
        ->assertPresent('[data-test="chart-parameter-option"]');

    $orderedOptions = $page->script(<<<'JS'
        () => Array.from(document.querySelectorAll('[data-test="chart-parameter-option"]'))
            .map((el) => el.textContent.replace(/\s+/g, ' ').trim())
    JS);

    expect($orderedOptions[0])->toContain('pH')
        ->and($orderedOptions[1])->toContain('Turbidez')
        ->and($orderedOptions[2])->toContain('Cloro');

    // Remove the loaded favorite series so we test manual adding from scratch
    $page->click('[data-test="chart-series-item"]:nth-of-type(3) button[title="Remover série"]')
        ->click('[data-test="chart-series-item"]:nth-of-type(2) button[title="Remover série"]')
        ->click('[data-test="chart-series-item"]:nth-of-type(1) button[title="Remover série"]')
        ->click('[data-test="favorites-toggle"]')
        ->assertAttribute('[data-test="favorites-toggle"]', 'aria-checked', 'false')
        ->assertNotPresent('[data-test="favorites-list"]')
        ->assertPresent('[data-test="save-favorite-btn"]')
        ->assertPresent('[data-test="chart-parameter-option"]')
        ->click('[data-test="chart-parameter-option"]:nth-of-type(1)')
        ->click('input[placeholder="Busque sistema, parâmetro, código ou tag..."]')
        ->click('[data-test="chart-parameter-option"]:nth-of-type(2)')
        ->assertPresent('[data-test="chart-series-editor"]')
        ->assertCount('[data-test="chart-series-item"]', 2);

    $layoutAndLimits = $page->script(<<<'JS'
        () => {
            const limitSwitches = Array.from(document.querySelectorAll('[data-test="series-show-limits"]'));
            const pointSwitches = Array.from(document.querySelectorAll('[data-test="series-show-points"]'));
            const valueSwitches = Array.from(document.querySelectorAll('[data-test="series-show-values"]'));
            const checkboxesOnRight = document.querySelectorAll('[data-test="chart-series-editor"] input[type="checkbox"]').length;
            const minInputs = Array.from(document.querySelectorAll('[data-test="series-min-val"]'));
            const maxInputs = Array.from(document.querySelectorAll('[data-test="series-max-val"]'));
            const saveFavBtn = document.querySelector('[data-test="save-favorite-btn"]');
            const titleInput = document.querySelector('[data-test="chart-title"]');
            const seriesEditor = document.querySelector('[data-test="chart-series-editor"]');
            const chartRenderer = document.querySelector('[data-report-chart-renderer]');

            return {
                firstLimitChecked: limitSwitches[0]?.getAttribute('aria-checked') ?? null,
                secondLimitChecked: limitSwitches[1]?.getAttribute('aria-checked') ?? null,
                firstPointsRole: pointSwitches[0]?.getAttribute('role') ?? null,
                firstValuesRole: valueSwitches[0]?.getAttribute('role') ?? null,
                checkboxesOnRight,
                firstMin: minInputs[0]?.value ?? null,
                firstMax: maxInputs[0]?.value ?? null,
                secondMin: minInputs[1]?.value ?? null,
                secondMax: maxInputs[1]?.value ?? null,
                saveOnRightOfTitle: saveFavBtn && titleInput
                    ? saveFavBtn.getBoundingClientRect().left > titleInput.getBoundingClientRect().left
                    : false,
                editorBelowChart: seriesEditor && chartRenderer
                    ? seriesEditor.getBoundingClientRect().top >= chartRenderer.getBoundingClientRect().bottom - 5
                    : false,
            };
        }
    JS);

    expect($layoutAndLimits['firstLimitChecked'])->toBe('true')
        ->and($layoutAndLimits['secondLimitChecked'])->toBe('false')
        ->and($layoutAndLimits['firstPointsRole'])->toBe('switch')
        ->and($layoutAndLimits['firstValuesRole'])->toBe('switch')
        ->and($layoutAndLimits['checkboxesOnRight'])->toBe(0)
        ->and($layoutAndLimits['firstMin'])->toBe('6.5')
        ->and($layoutAndLimits['firstMax'])->toBe('8.5')
        ->and($layoutAndLimits['secondMin'])->toBe('0')
        ->and($layoutAndLimits['secondMax'])->toBe('5')
        ->and($layoutAndLimits['saveOnRightOfTitle'])->toBeTrue()
        ->and($layoutAndLimits['editorBelowChart'])->toBeTrue();

    $page->click('[data-test="chart-series-item"]:nth-of-type(2) [data-test="series-show-limits"]')
        ->clear('[data-test="chart-title"]')
        ->type('[data-test="chart-title"]', 'Gráfico Teste Salvo')
        ->click('[data-test="save-favorite-btn"]')
        ->assertPresent('[data-test="save-as-new-favorite-btn"]')
        ->click('[data-test="favorites-toggle"]')
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
