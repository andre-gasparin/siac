<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue';
import AiDashboardModal from '@/features/dashboards/components/AiDashboardModal.vue';
import CreateDashboardModal from '@/features/dashboards/components/CreateDashboardModal.vue';
import DashboardGridCanvas from '@/features/dashboards/components/DashboardGridCanvas.vue';
import DashboardHeaderBar from '@/features/dashboards/components/DashboardHeaderBar.vue';
import DashboardToolbar from '@/features/dashboards/components/DashboardToolbar.vue';
import { useDashboardDateFilter } from '@/features/dashboards/composables/useDashboardDateFilter';
import {
    normalizeComponent,
    useDashboardGridCanvas,
} from '@/features/dashboards/composables/useDashboardGridCanvas';
import type { ComponentItem, DashboardItem } from '@/features/dashboards/types';
import dashboardsRoutes from '@/routes/dashboards';
import dashboardComponentsRoutes from '@/routes/dashboards/components';
import dashboardGridRoutes from '@/routes/dashboards/grid';
import type { Team } from '@/shared/types';

const props = defineProps<{
    dashboards: DashboardItem[];
    activeDashboard: (DashboardItem & { components: ComponentItem[] }) | null;
    currentTeam?: Team | null;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/dashboard`
                : '/',
        },
    ],
});

const currentTeamSlug = computed(() => props.currentTeam?.slug || 'default');

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

// Global Date Filter Composable
const { selectedPreset, dateRange } = useDashboardDateFilter('7d');

// Modals State
const showCreateDashboardModal = ref(false);
const showAiDashboardModal = ref(false);
const addingComponentType = ref<'indicator' | 'text' | 'chart' | null>(null);

// Grid Canvas Composable
const {
    localComponents,
    activeComponentId,
    isInteracting,
    canvasRef,
    isCompactCanvas,
    orderedComponents,
    componentRect,
    canvasHeight,
    draggingComponent,
    dragPreview,
    dragOriginRect,
    dropTargetId,
    resizingComponent,
    resizeHasCollision,
    startDragMove,
    startCornerResize,
    applyCompactedGrid,
    observeCanvasWidth,
} = useDashboardGridCanvas(saveGridConfig);

const gridCanvasRef =
    useTemplateRef<InstanceType<typeof DashboardGridCanvas>>('gridCanvasRef');

function syncCanvasRef() {
    if (gridCanvasRef.value?.canvasRef) {
        canvasRef.value = gridCanvasRef.value.canvasRef;
        observeCanvasWidth();
    }
}

// Initial components loading
localComponents.value = props.activeDashboard?.components
    ? props.activeDashboard.components.map((c, i) => normalizeComponent(c, i))
    : [];

function selectDashboard(id: number) {
    router.visit(
        dashboardsRoutes.show({
            current_team: currentTeamSlug.value,
            dashboard: id,
        }),
        {
            only: ['activeDashboard'],
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function deleteDashboard(dashboard: DashboardItem) {
    router.delete(
        dashboardsRoutes.destroy.url({
            current_team: currentTeamSlug.value,
            dashboard: dashboard.id,
        }),
    );
}

async function addComponent(type: 'indicator' | 'text' | 'chart') {
    if (!props.activeDashboard || addingComponentType.value !== null) {
        return;
    }

    addingComponentType.value = type;

    const defaultW = type === 'chart' ? 580 : 340;
    const defaultH = type === 'chart' ? 360 : 200;

    const payload = {
        type,
        grid_config: {
            x: 16,
            y: 16,
            w: defaultW,
            h: defaultH,
        },
        settings: {
            title:
                type === 'indicator'
                    ? 'Novo Indicador'
                    : type === 'chart'
                      ? 'Novo Gráfico'
                      : 'Novo Texto',
            ...(type === 'indicator' ? { aggregation: 'avg' } : {}),
            ...(type === 'chart' ? { series: [] } : {}),
            ...(type === 'text' ? { content: 'Digite o conteúdo...' } : {}),
        },
    };

    try {
        const response = await fetch(
            dashboardComponentsRoutes.store.url({
                current_team: currentTeamSlug.value,
                dashboard: props.activeDashboard.id,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
            },
        );

        if (response.ok) {
            const newComp = await response.json();
            const insertionOffset = defaultH + 16;

            localComponents.value.forEach((component) => {
                component.grid_config.y += insertionOffset;
            });
            localComponents.value.unshift(normalizeComponent(newComp, 0));
            applyCompactedGrid();
            saveGridConfig();

            await nextTick();
            document
                .querySelector(`[data-dashboard-component="${newComp.id}"]`)
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } catch (e) {
        console.error('Erro ao adicionar componente', e);
    } finally {
        addingComponentType.value = null;
    }
}

async function saveComponentSettingsPayload({
    component,
    settings,
}: {
    component: ComponentItem;
    settings: Record<string, any>;
}) {
    if (!props.activeDashboard) {
        return;
    }

    try {
        const response = await fetch(
            dashboardComponentsRoutes.update.url({
                current_team: currentTeamSlug.value,
                dashboard: props.activeDashboard.id,
                component: component.id,
            }),
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ settings }),
            },
        );

        if (response.ok) {
            const updated = await response.json();
            const index = localComponents.value.findIndex(
                (c) => c.id === updated.id,
            );

            if (index !== -1) {
                localComponents.value[index] = {
                    ...localComponents.value[index],
                    settings: updated.settings,
                };
            }
        }
    } catch (e) {
        console.error('Erro ao salvar settings do componente', e);
    }
}

async function handleDeleteComponent(comp: ComponentItem) {
    if (!props.activeDashboard) {
        return;
    }

    try {
        const response = await fetch(
            dashboardComponentsRoutes.destroy.url({
                current_team: currentTeamSlug.value,
                dashboard: props.activeDashboard.id,
                component: comp.id,
            }),
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        if (response.ok) {
            localComponents.value = localComponents.value.filter(
                (c) => c.id !== comp.id,
            );
            applyCompactedGrid();
            saveGridConfig();
        }
    } catch (e) {
        console.error('Erro ao excluir componente', e);
    }
}

async function saveGridConfig() {
    if (!props.activeDashboard || localComponents.value.length === 0) {
        return;
    }

    const payload = {
        components: localComponents.value.map((c) => ({
            id: c.id,
            grid_config: c.grid_config,
        })),
    };

    try {
        await fetch(
            dashboardGridRoutes.update.url({
                current_team: currentTeamSlug.value,
                dashboard: props.activeDashboard.id,
            }),
            {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
            },
        );
    } catch (e) {
        console.error('Erro ao salvar layout do grid', e);
    }
}

watch(
    () => props.activeDashboard,
    async (dashboard) => {
        localComponents.value = dashboard?.components
            ? dashboard.components.map((component, index) =>
                  normalizeComponent(component, index),
              )
            : [];
        await nextTick();
        syncCanvasRef();
    },
);

onMounted(async () => {
    await nextTick();
    syncCanvasRef();
});
</script>

<template>
    <Head title="Dashboards Dinâmicos" />

    <!-- Modals -->
    <CreateDashboardModal
        :show="showCreateDashboardModal"
        :current-team-slug="currentTeamSlug"
        @close="showCreateDashboardModal = false"
    />

    <AiDashboardModal
        :show="showAiDashboardModal"
        :current-team-slug="currentTeamSlug"
        @close="showAiDashboardModal = false"
    />

    <div
        class="min-h-full min-w-0 overflow-x-clip bg-slate-50/70 p-3 sm:p-4 md:p-6 dark:bg-neutral-950"
    >
        <div class="mx-auto flex max-w-7xl min-w-0 flex-col gap-5">
            <!-- Header Bar -->
            <DashboardHeaderBar
                :dashboards="dashboards"
                :active-dashboard="activeDashboard"
                @select-dashboard="selectDashboard"
                @open-ai-modal="showAiDashboardModal = true"
                @open-create-modal="showCreateDashboardModal = true"
                @delete-dashboard="deleteDashboard"
            />

            <!-- Toolbar: Global Date Filter & Add Components -->
            <DashboardToolbar
                v-model:selected-preset="selectedPreset"
                :has-active-dashboard="Boolean(activeDashboard)"
                :adding-component-type="addingComponentType"
                @add-component="addComponent"
            />

            <!-- Responsive compacted grid canvas -->
            <DashboardGridCanvas
                v-if="activeDashboard"
                ref="gridCanvasRef"
                :local-components="localComponents"
                :ordered-components="orderedComponents"
                :active-component-id="activeComponentId"
                :is-interacting="isInteracting"
                :canvas-height="canvasHeight"
                :is-compact-canvas="isCompactCanvas"
                :dragging-component="draggingComponent"
                :drag-preview="dragPreview"
                :drag-origin-rect="dragOriginRect"
                :drop-target-id="dropTargetId"
                :resizing-component="resizingComponent"
                :resize-has-collision="resizeHasCollision"
                :component-rect="componentRect"
                :current-team-slug="currentTeamSlug"
                :start-date="dateRange.start"
                :end-date="dateRange.end"
                @set-active-component-id="(id) => (activeComponentId = id)"
                @start-drag-move="startDragMove"
                @start-corner-resize="startCornerResize"
                @save-settings="saveComponentSettingsPayload"
                @delete-component="handleDeleteComponent"
            />
        </div>
    </div>
</template>
