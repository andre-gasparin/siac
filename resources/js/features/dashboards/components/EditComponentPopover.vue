<script setup lang="ts">
import { Plus, Trash2, X, Search, Loader2, Settings } from '@lucide/vue';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

interface ComponentItem {
    id?: number;
    type: 'indicator' | 'text' | 'chart';
    grid_config?: any;
    settings?: Record<string, any>;
}

const props = defineProps<{
    show: boolean;
    component: ComponentItem | null;
    currentTeamSlug: string;
    anchorElement: HTMLElement | null;
    parameterDisplayName?: string | null;
    chartSeriesContext?: Array<{
        parameter_id: number;
        parameter_display_name?: string | null;
    }>;
}>();

const emit = defineEmits(['close', 'save']);

const formTitle = ref('');
const aggregation = ref('avg');
const selectedParameterId = ref<number | null>(null);
const selectedParameterName = ref('');
const textContent = ref('');

// Chart Series Config
const seriesList = ref<
    Array<{
        parameter_id: number;
        label: string;
        chart_type: 'line' | 'bar' | 'area';
        color: string;
        stroke_width: number;
        independent_axis: boolean;
        axis_position: 'left' | 'right';
        min_val: number | null;
        max_val: number | null;
        parameter_display_name?: string | null;
    }>
>([]);

// Autocomplete parameter search
const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);

const popoverRef = ref<HTMLDivElement | null>(null);
const popoverPosition = ref({ top: '12px', left: '12px' });

async function updatePopoverPosition() {
    if (!props.show || !props.anchorElement) {
        return;
    }

    await nextTick();

    if (!popoverRef.value) {
        return;
    }

    const viewportPadding = 12;
    const anchorRect = props.anchorElement.getBoundingClientRect();
    const popoverRect = popoverRef.value.getBoundingClientRect();
    const maximumLeft = Math.max(
        viewportPadding,
        window.innerWidth - popoverRect.width - viewportPadding,
    );
    const left = Math.min(
        maximumLeft,
        Math.max(viewportPadding, anchorRect.right - popoverRect.width),
    );
    const maximumTop = Math.max(
        viewportPadding,
        window.innerHeight - popoverRect.height - viewportPadding,
    );
    const top = Math.min(
        maximumTop,
        Math.max(viewportPadding, anchorRect.bottom + 8),
    );

    popoverPosition.value = {
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
    };
}

async function searchParameters() {
    if (!searchQuery.value.trim()) {
        searchResults.value = [];

        return;
    }

    isSearching.value = true;

    try {
        const response = await fetch(
            `/${props.currentTeamSlug}/parameters/search?q=${encodeURIComponent(searchQuery.value)}`,
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            searchResults.value = await response.json();
        }
    } catch (e) {
        console.error(e);
    } finally {
        isSearching.value = false;
    }
}

function selectIndicatorParameter(param: any) {
    selectedParameterId.value = param.id;
    selectedParameterName.value =
        param.display_name || `${param.system_name} - ${param.name}`;
    searchResults.value = [];
    searchQuery.value = '';
}

function addChartSeries(param: any) {
    const parameterDisplayName =
        param.display_name || `${param.system_name} - ${param.name}`;

    seriesList.value.push({
        parameter_id: param.id,
        label: parameterDisplayName,
        chart_type: 'line',
        color: '#3B82F6',
        stroke_width: 2,
        independent_axis: false,
        axis_position: 'left',
        min_val: null,
        max_val: null,
        parameter_display_name: parameterDisplayName,
    });
    searchResults.value = [];
    searchQuery.value = '';
}

function removeSeries(index: number) {
    seriesList.value.splice(index, 1);
}

watch(
    () =>
        [
            props.component,
            props.parameterDisplayName,
            props.chartSeriesContext,
        ] as const,
    ([newComp, parameterDisplayName, chartSeriesContext]) => {
        if (!newComp) {
            return;
        }

        const s = newComp.settings || {};
        formTitle.value = s.title || '';
        aggregation.value = s.aggregation || 'avg';
        selectedParameterId.value = s.parameter_id || null;
        selectedParameterName.value = s.parameter_id
            ? parameterDisplayName || 'Parâmetro não encontrado'
            : '';
        textContent.value = s.content || '';
        const seriesContextByParameterId = new Map(
            (chartSeriesContext || []).map((series) => [
                series.parameter_id,
                series.parameter_display_name,
            ]),
        );
        seriesList.value = s.series
            ? JSON.parse(JSON.stringify(s.series)).map((series: any) => ({
                  ...series,
                  parameter_display_name:
                      seriesContextByParameterId.get(series.parameter_id) ||
                      'Parâmetro não encontrado',
              }))
            : [];
    },
    { immediate: true },
);

function handleSave() {
    const settingsPayload: Record<string, any> = {
        title: formTitle.value,
    };

    if (props.component?.type === 'indicator') {
        settingsPayload.aggregation = aggregation.value;
        settingsPayload.parameter_id = selectedParameterId.value;
    } else if (props.component?.type === 'text') {
        settingsPayload.content = textContent.value;
    } else if (props.component?.type === 'chart') {
        settingsPayload.series = seriesList.value.map((series) => {
            const seriesSettings = { ...series };

            delete seriesSettings.parameter_display_name;

            return seriesSettings;
        });
    }

    emit('save', settingsPayload);
}

function handleClickOutside(event: MouseEvent) {
    const target = event.target as Node;

    if (
        popoverRef.value &&
        !popoverRef.value.contains(target) &&
        !props.anchorElement?.contains(target)
    ) {
        emit('close');
    }
}

function removePopoverListeners() {
    window.removeEventListener('mousedown', handleClickOutside);
    window.removeEventListener('resize', updatePopoverPosition);
    window.removeEventListener('scroll', updatePopoverPosition);
}

watch(
    () => props.show,
    async (isShown) => {
        removePopoverListeners();

        if (!isShown) {
            return;
        }

        await updatePopoverPosition();

        window.addEventListener('mousedown', handleClickOutside);
        window.addEventListener('resize', updatePopoverPosition);
        window.addEventListener('scroll', updatePopoverPosition, {
            passive: true,
        });
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    removePopoverListeners();
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            ref="popoverRef"
            :style="popoverPosition"
            @mousedown.stop
            class="fixed z-[100] max-h-[calc(100vh-1.5rem)] w-[min(24rem,calc(100vw-1.5rem))] animate-in overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 shadow-2xl fade-in-50 zoom-in-95 dark:border-neutral-800 dark:bg-neutral-900"
        >
            <!-- Tooltip Header -->
            <div
                class="flex items-center justify-between border-b pb-2 dark:border-neutral-800"
            >
                <div
                    class="flex items-center gap-1.5 text-xs font-bold text-slate-900 dark:text-white"
                >
                    <Settings class="size-3.5 text-primary" />
                    <span
                        >Configurar ({{ component?.type?.toUpperCase() }})</span
                    >
                </div>
                <button
                    @click="emit('close')"
                    class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-neutral-800 dark:hover:text-white"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Tooltip Options Content -->
            <div class="mt-3 max-h-[60vh] space-y-3.5 overflow-y-auto pr-1">
                <!-- Title Field -->
                <div>
                    <label
                        class="block text-[11px] font-medium text-slate-700 dark:text-neutral-300"
                    >
                        Título do Componente
                    </label>
                    <input
                        v-model="formTitle"
                        type="text"
                        placeholder="Ex: Vazão Principal"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-xs shadow-xs focus:border-primary focus:outline-hidden dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                    />
                </div>

                <!-- Indicator Settings -->
                <div v-if="component?.type === 'indicator'" class="space-y-3">
                    <div>
                        <label
                            class="block text-[11px] font-medium text-slate-700 dark:text-neutral-300"
                        >
                            Agregação
                        </label>
                        <select
                            v-model="aggregation"
                            class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                        >
                            <option value="avg">Média (AVG)</option>
                            <option value="sum">Soma (SUM)</option>
                            <option value="count">Contagem (COUNT)</option>
                            <option value="max">Máximo (MAX)</option>
                            <option value="min">Mínimo (MIN)</option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-[11px] font-medium text-slate-700 dark:text-neutral-300"
                        >
                            Parâmetro Monitorado
                        </label>
                        <div
                            v-if="selectedParameterName"
                            class="mt-1 flex items-center justify-between rounded-md bg-primary/10 px-2.5 py-1.5 text-xs font-medium text-primary"
                        >
                            <span class="truncate pr-1">{{
                                selectedParameterName
                            }}</span>
                            <button
                                @click="
                                    selectedParameterId = null;
                                    selectedParameterName = '';
                                "
                                class="shrink-0 text-[10px] underline"
                            >
                                Alterar
                            </button>
                        </div>
                        <div v-else class="relative mt-1">
                            <div
                                class="flex items-center rounded-md border border-slate-300 px-2.5 py-1 dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <Search
                                    class="mr-1.5 size-3.5 shrink-0 text-muted-foreground"
                                />
                                <input
                                    v-model="searchQuery"
                                    @input="searchParameters"
                                    type="text"
                                    placeholder="Buscar parâmetro..."
                                    class="w-full bg-transparent text-xs focus:outline-hidden dark:text-white"
                                />
                                <Loader2
                                    v-if="isSearching"
                                    class="size-3.5 shrink-0 animate-spin text-primary"
                                />
                            </div>

                            <div
                                v-if="searchResults.length > 0"
                                class="absolute right-0 left-0 z-50 mt-1 max-h-40 overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div
                                    v-for="p in searchResults"
                                    :key="p.id"
                                    @click="selectIndicatorParameter(p)"
                                    class="cursor-pointer rounded px-2 py-1.5 text-xs hover:bg-primary/10 hover:text-primary dark:text-neutral-200"
                                >
                                    <div
                                        class="text-[11px] font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ p.display_name }}
                                    </div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Tag: {{ p.tag || p.code || 'N/A' }}
                                        <span v-if="p.unit"
                                            >• {{ p.unit }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Text Settings -->
                <div v-else-if="component?.type === 'text'">
                    <label
                        class="block text-[11px] font-medium text-slate-700 dark:text-neutral-300"
                    >
                        Conteúdo (Texto / Markdown)
                    </label>
                    <textarea
                        v-model="textContent"
                        rows="3"
                        placeholder="Digite as instruções..."
                        class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-xs shadow-xs focus:border-primary focus:outline-hidden dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                    ></textarea>
                </div>

                <!-- Chart Settings -->
                <div v-else-if="component?.type === 'chart'" class="space-y-3">
                    <div>
                        <label
                            class="block text-[11px] font-medium text-slate-700 dark:text-neutral-300"
                        >
                            Adicionar Parâmetro à Série
                        </label>
                        <div class="relative mt-1">
                            <div
                                class="flex items-center rounded-md border border-slate-300 px-2.5 py-1 dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <Search
                                    class="mr-1.5 size-3.5 shrink-0 text-muted-foreground"
                                />
                                <input
                                    v-model="searchQuery"
                                    @input="searchParameters"
                                    type="text"
                                    placeholder="Buscar parâmetro..."
                                    class="w-full bg-transparent text-xs focus:outline-hidden dark:text-white"
                                />
                                <Loader2
                                    v-if="isSearching"
                                    class="size-3.5 shrink-0 animate-spin text-primary"
                                />
                            </div>

                            <div
                                v-if="searchResults.length > 0"
                                class="absolute right-0 left-0 z-50 mt-1 max-h-40 overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div
                                    v-for="p in searchResults"
                                    :key="p.id"
                                    @click="addChartSeries(p)"
                                    class="flex cursor-pointer items-center justify-between rounded px-2 py-1.5 text-xs hover:bg-primary/10 hover:text-primary dark:text-neutral-200"
                                >
                                    <div>
                                        <div
                                            class="text-[11px] font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ p.display_name }}
                                        </div>
                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Tag: {{ p.tag || p.code || 'N/A' }}
                                        </div>
                                    </div>
                                    <Plus class="size-3.5 text-primary" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Series Configurations List -->
                    <div class="space-y-2">
                        <h4
                            class="text-[11px] font-semibold text-slate-900 dark:text-white"
                        >
                            Séries ({{ seriesList.length }})
                        </h4>
                        <div
                            v-if="seriesList.length === 0"
                            class="text-[11px] text-muted-foreground italic"
                        >
                            Nenhuma série configurada.
                        </div>

                        <div
                            v-for="(s, idx) in seriesList"
                            :key="idx"
                            class="space-y-1.5 rounded-lg border border-slate-200 bg-slate-50/70 p-2.5 text-xs dark:border-neutral-800 dark:bg-neutral-950"
                        >
                            <div class="flex items-center justify-between">
                                <input
                                    v-model="s.label"
                                    type="text"
                                    placeholder="Nome da Legenda"
                                    class="w-full border-b border-slate-200 bg-transparent pb-0.5 text-xs font-semibold text-slate-900 focus:border-primary focus:outline-hidden dark:border-neutral-700 dark:text-white"
                                />
                                <button
                                    @click="removeSeries(idx)"
                                    class="ml-2 text-red-500 hover:text-red-700"
                                >
                                    <Trash2 class="size-3" />
                                </button>
                            </div>

                            <p
                                class="truncate text-[10px] text-muted-foreground"
                                :title="s.parameter_display_name || undefined"
                            >
                                {{ s.parameter_display_name }}
                            </p>

                            <div class="grid grid-cols-2 gap-1.5">
                                <div>
                                    <label
                                        class="block text-[9px] text-muted-foreground"
                                        >Tipo</label
                                    >
                                    <select
                                        v-model="s.chart_type"
                                        class="w-full rounded border p-1 text-[11px] dark:bg-neutral-800 dark:text-white"
                                    >
                                        <option value="line">Linha</option>
                                        <option value="bar">Barra</option>
                                        <option value="area">Área</option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="block text-[9px] text-muted-foreground"
                                        >Cor</label
                                    >
                                    <input
                                        v-model="s.color"
                                        type="color"
                                        class="h-6 w-full cursor-pointer rounded border p-0.5"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-1.5">
                                <div>
                                    <label
                                        class="block text-[9px] text-muted-foreground"
                                        >Espessura</label
                                    >
                                    <input
                                        v-model.number="s.stroke_width"
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="w-full rounded border p-1 text-[11px] dark:bg-neutral-800 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-[9px] text-muted-foreground"
                                        >Eixo Y</label
                                    >
                                    <select
                                        v-model="s.axis_position"
                                        class="w-full rounded border p-1 text-[11px] dark:bg-neutral-800 dark:text-white"
                                    >
                                        <option value="left">Esquerda</option>
                                        <option value="right">Direita</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 pt-0.5">
                                <input
                                    v-model="s.independent_axis"
                                    type="checkbox"
                                    :id="`pop-indep-${idx}`"
                                />
                                <label
                                    :for="`pop-indep-${idx}`"
                                    class="text-[10px] text-slate-700 dark:text-neutral-300"
                                    >Eixo Y Independente</label
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tooltip Footer Actions -->
            <div
                class="mt-3 flex items-center justify-end gap-1.5 border-t pt-2 dark:border-neutral-800"
            >
                <button
                    @click="emit('close')"
                    class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                >
                    Cancelar
                </button>
                <button
                    @click="handleSave"
                    class="rounded-md bg-primary px-3 py-1 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Salvar
                </button>
            </div>
        </div>
    </Teleport>
</template>
