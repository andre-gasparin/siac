<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    BarChart3,
    BoxSelect,
    Check,
    FileText,
    GripHorizontal,
    Loader2,
    Pencil,
    RotateCcw,
    Trash2,
    X,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import { nextTick, onMounted, ref, watch } from 'vue';
import EditComponentPopover from '@/features/dashboards/components/EditComponentPopover.vue';
import dashboardComponentsRoutes from '@/routes/dashboards/components';
import EChartRenderer from '@/shared/components/charts/EChartRenderer.vue';

interface GridConfig {
    x: number;
    y: number;
    w: number;
    h: number;
}

interface ComponentProps {
    component: {
        id: number;
        type: 'indicator' | 'text' | 'chart';
        grid_config: GridConfig;
        settings: Record<string, any>;
    };
    currentTeamSlug: string;
    startDate?: string;
    endDate?: string;
}

const props = defineProps<ComponentProps>();
const emit = defineEmits(['edit', 'delete', 'drag-start', 'save-settings']);

const loading = ref(true);
const error = ref<string | null>(null);
const dataPayload = ref<any>(null);
const showPopover = ref(false);
const editButtonRef = ref<HTMLElement | null>(null);
const deleteButtonRef = ref<HTMLElement | null>(null);
const deleteConfirmationRef = ref<HTMLDivElement | null>(null);
const showDeleteConfirmation = ref(false);
const deleteConfirmationPosition = ref({ top: '12px', left: '12px' });

const echartRef = ref<any>(null);

function handleToggleBoxZoom() {
    echartRef.value?.toggleBoxZoom();
}

function handleZoomIn() {
    echartRef.value?.zoomIn();
}

function handleZoomOut() {
    echartRef.value?.zoomOut();
}

function handleResetZoom() {
    echartRef.value?.resetZoom();
}

function handleSavePopover(settings: Record<string, any>) {
    emit('save-settings', { component: props.component, settings });
    showPopover.value = false;
}

async function toggleDeleteConfirmation() {
    showDeleteConfirmation.value = !showDeleteConfirmation.value;
    showPopover.value = false;

    if (!showDeleteConfirmation.value) {
        return;
    }

    await nextTick();

    if (!deleteButtonRef.value || !deleteConfirmationRef.value) {
        return;
    }

    const viewportPadding = 8;
    const anchorRect = deleteButtonRef.value.getBoundingClientRect();
    const confirmationRect =
        deleteConfirmationRef.value.getBoundingClientRect();
    const maximumLeft = Math.max(
        viewportPadding,
        window.innerWidth - confirmationRect.width - viewportPadding,
    );
    const left = Math.min(
        maximumLeft,
        Math.max(viewportPadding, anchorRect.right - confirmationRect.width),
    );
    const preferredTop = anchorRect.bottom + 6;
    const top =
        preferredTop + confirmationRect.height <=
        window.innerHeight - viewportPadding
            ? preferredTop
            : Math.max(
                  viewportPadding,
                  anchorRect.top - confirmationRect.height - 6,
              );

    deleteConfirmationPosition.value = {
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
    };
}

function confirmComponentDeletion() {
    showDeleteConfirmation.value = false;
    emit('delete', props.component);
}

async function fetchData() {
    loading.value = true;
    error.value = null;

    try {
        const query: Record<string, string> = {};

        if (props.startDate) {
            query.start_date = props.startDate;
        }

        if (props.endDate) {
            query.end_date = props.endDate;
        }

        const response = await fetch(
            dashboardComponentsRoutes.data.url(
                {
                    current_team: props.currentTeamSlug,
                    component: props.component.id,
                },
                { query },
            ),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP Error ${response.status}`);
        }

        dataPayload.value = await response.json();
    } catch (e: any) {
        error.value = e.message || 'Erro ao carregar dados';
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.startDate, props.endDate, props.component.settings],
    () => {
        fetchData();
    },
    { deep: true },
);

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div
        class="group relative flex h-full w-full flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-shadow select-none hover:shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
    >
        <!-- Header Actions & Drag Handle -->
        <div
            @pointerdown="emit('drag-start', $event)"
            class="mb-2 flex cursor-grab touch-none items-center justify-between border-b border-slate-100 pb-2 active:cursor-grabbing dark:border-neutral-800/80"
        >
            <div class="flex items-center gap-2 truncate pr-2">
                <!-- Drag Grip Handle -->
                <span
                    class="text-slate-300 hover:text-primary dark:text-neutral-700 dark:hover:text-primary"
                >
                    <GripHorizontal class="size-4" />
                </span>
                <span
                    class="flex size-6 items-center justify-center rounded-md bg-primary/10 text-primary"
                >
                    <Activity
                        v-if="component.type === 'indicator'"
                        class="size-3.5"
                    />
                    <BarChart3
                        v-else-if="component.type === 'chart'"
                        class="size-3.5"
                    />
                    <FileText v-else class="size-3.5" />
                </span>
                <h3
                    class="truncate text-xs font-bold text-slate-900 dark:text-white"
                >
                    {{
                        dataPayload?.title ||
                        component.settings?.title ||
                        'Componente'
                    }}
                </h3>
            </div>

            <!-- Direct Action Icons (Zoom Controls, Pencil Tooltip & Trash) -->
            <div
                @pointerdown.stop
                class="relative flex items-center gap-1 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
            >
                <!-- Chart Zoom Controls -->
                <template v-if="component.type === 'chart'">
                    <button
                        @click.stop="handleToggleBoxZoom"
                        :title="
                            echartRef?.isBoxZoomActive
                                ? 'Desativar Seleção de Zoom'
                                : 'Zoom por Seleção de Área (Desenhar retângulo)'
                        "
                        class="flex size-6 items-center justify-center rounded transition-colors"
                        :class="
                            echartRef?.isBoxZoomActive
                                ? 'bg-primary font-bold text-primary-foreground'
                                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white'
                        "
                    >
                        <BoxSelect class="size-3" />
                    </button>
                    <button
                        @click.stop="handleZoomIn"
                        title="Aumentar Zoom (+)"
                        class="flex size-6 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                    >
                        <ZoomIn class="size-3" />
                    </button>
                    <button
                        @click.stop="handleZoomOut"
                        title="Reduzir Zoom (-)"
                        class="flex size-6 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                    >
                        <ZoomOut class="size-3" />
                    </button>
                    <button
                        @click.stop="handleResetZoom"
                        title="Restaurar Zoom Inicial"
                        class="mr-1 flex size-6 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                    >
                        <RotateCcw class="size-3" />
                    </button>
                </template>

                <button
                    ref="editButtonRef"
                    @click.stop="showPopover = !showPopover"
                    title="Editar Componente (Opções no Tooltip)"
                    class="flex size-6 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                >
                    <Pencil class="size-3" />
                </button>
                <button
                    ref="deleteButtonRef"
                    @click.stop="toggleDeleteConfirmation"
                    title="Excluir Componente"
                    class="flex size-6 items-center justify-center rounded text-red-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                >
                    <Trash2 class="size-3" />
                </button>

                <!-- Tooltip Options Popover -->
                <EditComponentPopover
                    :show="showPopover"
                    :component="component"
                    :current-team-slug="currentTeamSlug"
                    :anchor-element="editButtonRef"
                    :parameter-display-name="
                        dataPayload?.parameter_display_name
                    "
                    :chart-series-context="dataPayload?.series"
                    @close="showPopover = false"
                    @save="handleSavePopover"
                />
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showDeleteConfirmation"
                ref="deleteConfirmationRef"
                role="dialog"
                aria-label="Confirmar exclusão do componente"
                class="fixed z-[110] flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-medium text-slate-700 shadow-xl dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200"
                :style="deleteConfirmationPosition"
                @pointerdown.stop
                @click.stop
            >
                <span class="px-1">Excluir?</span>
                <button
                    type="button"
                    aria-label="Confirmar exclusão"
                    title="Confirmar exclusão"
                    class="flex size-7 items-center justify-center rounded-md bg-red-600 text-white hover:bg-red-700"
                    @click="confirmComponentDeletion"
                >
                    <Check class="size-4" />
                </button>
                <button
                    type="button"
                    aria-label="Cancelar exclusão"
                    title="Cancelar exclusão"
                    class="flex size-7 items-center justify-center rounded-md bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700"
                    @click="showDeleteConfirmation = false"
                >
                    <X class="size-4" />
                </button>
            </div>
        </Teleport>

        <!-- Skeleton Loading State -->
        <div
            v-if="loading"
            class="flex flex-1 flex-col items-center justify-center py-4"
        >
            <Loader2 class="size-5 animate-spin text-primary/70" />
            <span class="mt-2 text-[11px] text-muted-foreground"
                >Carregando dados...</span
            >
        </div>

        <!-- Error State -->
        <div
            v-else-if="error"
            class="flex flex-1 flex-col items-center justify-center p-2 text-center text-xs text-red-500"
        >
            <AlertCircle class="mb-1 size-4" />
            <span>{{ error }}</span>
        </div>

        <!-- Component Content Render -->
        <div
            v-else-if="dataPayload"
            class="flex flex-1 flex-col justify-between overflow-hidden"
        >
            <!-- Indicator Type -->
            <div
                v-if="component.type === 'indicator'"
                class="my-auto text-center"
            >
                <div
                    class="text-3xl font-extrabold text-slate-900 dark:text-white"
                >
                    {{ dataPayload.formatted_value }}
                    <span
                        v-if="dataPayload.unit"
                        class="ml-0.5 text-sm font-normal text-muted-foreground"
                    >
                        {{ dataPayload.unit }}
                    </span>
                </div>
                <div class="mt-2 flex items-center justify-center gap-1.5">
                    <span
                        class="rounded bg-primary/10 px-2 py-0.5 text-[10px] font-semibold tracking-wider text-primary uppercase"
                    >
                        Agregação: {{ dataPayload.aggregation }}
                    </span>
                </div>
            </div>

            <!-- Text Type -->
            <div
                v-else-if="component.type === 'text'"
                class="prose prose-sm dark:prose-invert max-w-none overflow-y-auto"
            >
                <div
                    class="text-xs whitespace-pre-wrap text-slate-600 dark:text-neutral-300"
                >
                    {{ dataPayload.content || 'Nenhum conteúdo definido.' }}
                </div>
            </div>

            <!-- Chart Type: Rendered via Apache ECharts with dataZoom minimap and interactive zoom -->
            <div
                v-else-if="component.type === 'chart'"
                class="flex h-full min-h-[140px] w-full flex-1 flex-col"
            >
                <div
                    v-if="
                        !dataPayload.series || dataPayload.series.length === 0
                    "
                    class="flex flex-1 items-center justify-center text-xs text-muted-foreground"
                >
                    Nenhuma série cadastrada.
                </div>
                <EChartRenderer
                    v-else
                    ref="echartRef"
                    :series="dataPayload.series"
                />
            </div>
        </div>
    </div>
</template>
