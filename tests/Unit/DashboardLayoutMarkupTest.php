<?php

test('dashboard drag interaction uses a collision threshold and pointer events', function () {
    $composable = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/composables/useDashboardGridCanvas.ts',
    );
    $canvas = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/DashboardGridCanvas.vue',
    );
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/DashboardComponentItem.vue',
    );

    expect($composable)
        ->not->toBeFalse()
        ->toContain('export const COLLISION_THRESHOLD = 0.5')
        ->toContain("window.addEventListener('pointermove'")
        ->toContain('dropTargetId.value = findCollision(')
        ->not->toContain('resolveCollisions');

    expect($canvas)
        ->not->toBeFalse()
        ->toContain('Solte para trocar');

    expect($component)
        ->not->toBeFalse()
        ->toContain('@pointerdown="emit(\'drag-start\', $event)"')
        ->toContain('@pointerdown.stop')
        ->toContain('@click.stop="toggleDeleteConfirmation"')
        ->toContain('touch-none');
});

test('dashboard mutations include csrf protection and passive pointer movement', function () {
    $dashboard = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/pages/Index.vue',
    );
    $composable = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/composables/useDashboardGridCanvas.ts',
    );
    $layout = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/app.blade.php',
    );

    expect($dashboard)
        ->not->toBeFalse()
        ->toContain("'X-CSRF-TOKEN': csrfToken()");

    expect($composable)
        ->not->toBeFalse()
        ->toContain("window.addEventListener('pointermove', onDragMoving, {")
        ->toContain('passive: true')
        ->not->toContain('passive: false');

    expect($layout)
        ->not->toBeFalse()
        ->toContain('<meta name="csrf-token" content="{{ csrf_token() }}">');
});

test('dashboard canvas contains cards and switches to a compact layout', function () {
    $composable = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/composables/useDashboardGridCanvas.ts',
    );
    $canvas = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/DashboardGridCanvas.vue',
    );

    expect($composable)
        ->not->toBeFalse()
        ->toContain('export const COMPACT_BREAKPOINT = 640')
        ->toContain('const gridColumnWidth = computed(')
        ->toContain('return 1;');

    expect($canvas)
        ->not->toBeFalse()
        ->toContain('data-dashboard-canvas')
        ->toContain('min-w-0')
        ->toContain('overflow-hidden');
});

test('dashboard component editor is teleported above the grid and clamped to the viewport', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/DashboardComponentItem.vue',
    );
    $popover = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/EditComponentPopover.vue',
    );

    expect($component)
        ->not->toBeFalse()
        ->toContain('ref="editButtonRef"')
        ->toContain(':anchor-element="editButtonRef"')
        ->toContain('dataPayload?.parameter_display_name');

    expect($popover)
        ->not->toBeFalse()
        ->toContain('<Teleport to="body">')
        ->toContain('class="fixed z-[100]')
        ->toContain('window.innerWidth - popoverRect.width')
        ->toContain('window.innerHeight - popoverRect.height');
});

test('indicator editor shows its parameter and system names instead of a numeric placeholder', function () {
    $popover = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/EditComponentPopover.vue',
    );

    expect($popover)
        ->not->toBeFalse()
        ->toContain("parameterDisplayName || 'Parâmetro não encontrado'")
        ->not->toContain('`Parâmetro #${s.parameter_id}`');
});

test('chart editor shows the system and parameter below each series name', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/DashboardComponentItem.vue',
    );
    $popover = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/EditComponentPopover.vue',
    );

    expect($component)
        ->not->toBeFalse()
        ->toContain(':chart-series-context="dataPayload?.series"');

    expect($popover)
        ->not->toBeFalse()
        ->toContain('parameter_display_name?: string | null')
        ->toContain('{{ s.parameter_display_name }}')
        ->toContain('seriesContextByParameterId.get(series.parameter_id)');
});

test('dashboard grid snaps cards to fixed columns and compacts empty vertical space', function () {
    $composable = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/composables/useDashboardGridCanvas.ts',
    );

    expect($composable)
        ->not->toBeFalse()
        ->toContain('export const DESKTOP_COLUMN_COUNT = 12')
        ->toContain('export const TABLET_COLUMN_COUNT = 6')
        ->toContain('export const GRID_Y_STEP = 20')
        ->toContain('function snappedGridX(')
        ->toContain('function applyCompactedGrid()')
        ->toContain('rect.y += GRID_Y_STEP')
        ->toContain('applyCompactedGrid();');
});

test('new dashboard components are inserted at the top and scrolled into view', function () {
    $dashboard = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/pages/Index.vue',
    );

    expect($dashboard)
        ->not->toBeFalse()
        ->toContain('x: 16')
        ->toContain('component.grid_config.y += insertionOffset')
        ->toContain('localComponents.value.unshift(')
        ->toContain("?.scrollIntoView({ behavior: 'smooth', block: 'center' })")
        ->not->toContain('Position newly added component below existing components');
});

test('dashboard creation uses inertia navigation without a browser reload', function () {
    $manualModal = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/CreateDashboardModal.vue',
    );
    $aiModal = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/AiDashboardModal.vue',
    );

    expect($manualModal)
        ->not->toBeFalse()
        ->toContain('dashboardsRoutes.store.url(')
        ->toContain('preserveState: true')
        ->toContain('preserveScroll: true');

    expect($aiModal)
        ->not->toBeFalse()
        ->toContain('dashboardsRoutes.aiSuggest.url(')
        ->toContain('dashboardsRoutes.aiCreate.url(')
        ->toContain('router.visit(res.redirect_url')
        ->not->toContain('window.location')
        ->not->toContain('router.reload()');
});

test('dashboard switching uses a partial inertia visit', function () {
    $dashboard = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/pages/Index.vue',
    );

    expect($dashboard)
        ->not->toBeFalse()
        ->toContain('router.visit(')
        ->toContain('dashboardsRoutes.show({')
        ->toContain("only: ['activeDashboard']")
        ->toContain('preserveState: true')
        ->toContain('preserveScroll: true')
        ->not->toContain('router.get(\n        dashboardsRoutes.show.url(');
});

test('add component buttons show a loading state and prevent duplicate requests', function () {
    $dashboard = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/pages/Index.vue',
    );
    $toolbar = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/DashboardToolbar.vue',
    );

    expect($dashboard)
        ->not->toBeFalse()
        ->toContain("const addingComponentType = ref<'indicator' | 'text' | 'chart' | null>(")
        ->toContain('addingComponentType.value = type')
        ->toContain('addingComponentType.value = null');

    expect($toolbar)
        ->not->toBeFalse()
        ->toContain(':disabled="addingComponentType !== null"')
        ->toContain(':aria-busy="addingComponentType === \'indicator\'"')
        ->toContain('class="size-3.5 animate-spin"')
        ->toContain("? 'Adicionando...'");
});

test('dashboard deletions use check and x tooltips instead of javascript confirms', function () {
    $header = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/DashboardHeaderBar.vue',
    );
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/dashboards/components/DashboardComponentItem.vue',
    );

    expect($header)
        ->not->toBeFalse()
        ->toContain('showDeleteConfirmation')
        ->toContain('aria-label="Confirmar exclusão do dashboard"')
        ->toContain('aria-label="Cancelar exclusão do dashboard"')
        ->not->toContain('confirm(');

    expect($component)
        ->not->toBeFalse()
        ->toContain('<Teleport to="body">')
        ->toContain('aria-label="Confirmar exclusão do componente"')
        ->toContain('aria-label="Cancelar exclusão"')
        ->toContain('class="fixed z-[110]')
        ->not->toContain('confirm(');
});
