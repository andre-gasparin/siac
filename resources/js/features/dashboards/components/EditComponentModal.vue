<script setup lang="ts">
import { Plus, Trash2, X, Search, Loader2 } from '@lucide/vue';
import { ref, watch } from 'vue';

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
    }>
>([]);

// Autocomplete parameter search
const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);

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
    seriesList.value.push({
        parameter_id: param.id,
        label: param.display_name || `${param.system_name} - ${param.name}`,
        chart_type: 'line',
        color: '#3B82F6',
        stroke_width: 2,
        independent_axis: false,
        axis_position: 'left',
        min_val: null,
        max_val: null,
    });
    searchResults.value = [];
    searchQuery.value = '';
}

function removeSeries(index: number) {
    seriesList.value.splice(index, 1);
}

watch(
    () => props.component,
    (newComp) => {
        if (!newComp) {
            return;
        }

        const s = newComp.settings || {};
        formTitle.value = s.title || '';
        aggregation.value = s.aggregation || 'avg';
        selectedParameterId.value = s.parameter_id || null;
        selectedParameterName.value = s.parameter_id
            ? `Parâmetro #${s.parameter_id}`
            : '';
        textContent.value = s.content || '';
        seriesList.value = s.series ? JSON.parse(JSON.stringify(s.series)) : [];
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
        settingsPayload.series = seriesList.value;
    }

    emit('save', settingsPayload);
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
    >
        <div
            class="relative w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-xl dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div
                class="flex items-center justify-between border-b pb-3 dark:border-neutral-800"
            >
                <h3
                    class="text-lg font-semibold text-slate-900 dark:text-white"
                >
                    Configurar Componente ({{ component?.type?.toUpperCase() }})
                </h3>
                <button
                    @click="emit('close')"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-neutral-800"
                >
                    <X class="size-5" />
                </button>
            </div>

            <div class="mt-4 max-h-[70vh] space-y-4 overflow-y-auto pr-1">
                <!-- Title Field -->
                <div>
                    <label
                        class="block text-xs font-medium text-slate-700 dark:text-neutral-300"
                    >
                        Título do Componente
                    </label>
                    <input
                        v-model="formTitle"
                        type="text"
                        placeholder="Ex: Vazão Principal"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                    />
                </div>

                <!-- Indicator Settings -->
                <div v-if="component?.type === 'indicator'" class="space-y-3">
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-700 dark:text-neutral-300"
                        >
                            Agregação
                        </label>
                        <select
                            v-model="aggregation"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                        >
                            <option value="avg">Média (AVG)</option>
                            <option value="sum">Soma (SUM)</option>
                            <option value="count">Contagem (COUNT)</option>
                            <option value="max">Máximo (MAX)</option>
                            <option value="min">Mínimo (MIN)</option>
                        </select>
                    </div>

                    <!-- Parameter Search Autocomplete (Sistema - Parâmetro) -->
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-700 dark:text-neutral-300"
                        >
                            Parâmetro Monitorado (do Time Logado)
                        </label>
                        <div
                            v-if="selectedParameterName"
                            class="mt-1 flex items-center justify-between rounded-md bg-primary/10 px-3 py-2 text-xs font-medium text-primary"
                        >
                            <span>{{ selectedParameterName }}</span>
                            <button
                                @click="
                                    selectedParameterId = null;
                                    selectedParameterName = '';
                                "
                                class="underline"
                            >
                                Alterar
                            </button>
                        </div>
                        <div v-else class="relative mt-1">
                            <div
                                class="flex items-center rounded-md border border-slate-300 px-3 py-1.5 dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <Search
                                    class="mr-2 size-4 text-muted-foreground"
                                />
                                <input
                                    v-model="searchQuery"
                                    @input="searchParameters"
                                    type="text"
                                    placeholder="Buscar por sistema, parâmetro ou tag..."
                                    class="w-full bg-transparent text-sm focus:outline-none dark:text-white"
                                />
                                <Loader2
                                    v-if="isSearching"
                                    class="size-4 animate-spin text-primary"
                                />
                            </div>

                            <div
                                v-if="searchResults.length > 0"
                                class="absolute right-0 left-0 z-30 mt-1 max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div
                                    v-for="p in searchResults"
                                    :key="p.id"
                                    @click="selectIndicatorParameter(p)"
                                    class="cursor-pointer rounded px-2.5 py-2 text-xs hover:bg-primary/10 hover:text-primary dark:text-neutral-200"
                                >
                                    <div
                                        class="font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ p.display_name }}
                                    </div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Tag: {{ p.tag || p.code || 'N/A' }}
                                        <span v-if="p.unit"
                                            >• Unidade: {{ p.unit }}</span
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
                        class="block text-xs font-medium text-slate-700 dark:text-neutral-300"
                    >
                        Conteúdo (Markdown / Texto Livre)
                    </label>
                    <textarea
                        v-model="textContent"
                        rows="4"
                        placeholder="Digite as instruções..."
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                    ></textarea>
                </div>

                <!-- Chart Settings -->
                <div v-else-if="component?.type === 'chart'" class="space-y-4">
                    <!-- Search & Add Parameter to Series -->
                    <div>
                        <label
                            class="block text-xs font-medium text-slate-700 dark:text-neutral-300"
                        >
                            Adicionar Séries por Autocompletar (Sistema -
                            Parâmetro)
                        </label>
                        <div class="relative mt-1">
                            <div
                                class="flex items-center rounded-md border border-slate-300 px-3 py-1.5 dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <Search
                                    class="mr-2 size-4 text-muted-foreground"
                                />
                                <input
                                    v-model="searchQuery"
                                    @input="searchParameters"
                                    type="text"
                                    placeholder="Buscar sistema ou parâmetro para adicionar série..."
                                    class="w-full bg-transparent text-sm focus:outline-none dark:text-white"
                                />
                                <Loader2
                                    v-if="isSearching"
                                    class="size-4 animate-spin text-primary"
                                />
                            </div>

                            <div
                                v-if="searchResults.length > 0"
                                class="absolute right-0 left-0 z-30 mt-1 max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div
                                    v-for="p in searchResults"
                                    :key="p.id"
                                    @click="addChartSeries(p)"
                                    class="flex cursor-pointer items-center justify-between rounded px-2.5 py-2 text-xs hover:bg-primary/10 hover:text-primary dark:text-neutral-200"
                                >
                                    <div>
                                        <div
                                            class="font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ p.display_name }}
                                        </div>
                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            Tag: {{ p.tag || p.code || 'N/A' }}
                                            <span v-if="p.unit"
                                                >• Unidade: {{ p.unit }}</span
                                            >
                                        </div>
                                    </div>
                                    <Plus class="size-4 text-primary" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Series Configurations List -->
                    <div class="space-y-3">
                        <h4
                            class="text-xs font-semibold text-slate-900 dark:text-white"
                        >
                            Séries / Legendas Configuradas ({{
                                seriesList.length
                            }})
                        </h4>
                        <div
                            v-if="seriesList.length === 0"
                            class="text-xs text-muted-foreground italic"
                        >
                            Nenhuma série configurada ainda. Use a busca acima
                            para adicionar.
                        </div>

                        <div
                            v-for="(s, idx) in seriesList"
                            :key="idx"
                            class="space-y-2 rounded-lg border border-slate-200 bg-slate-50/50 p-3 text-xs dark:border-neutral-800 dark:bg-neutral-950"
                        >
                            <div class="flex items-center justify-between">
                                <input
                                    v-model="s.label"
                                    type="text"
                                    placeholder="Nome da Legenda"
                                    class="w-full border-b border-slate-200 bg-transparent pb-0.5 font-semibold text-slate-900 focus:border-primary focus:outline-none dark:border-neutral-700 dark:text-white"
                                />
                                <button
                                    @click="removeSeries(idx)"
                                    class="ml-2 text-red-500 hover:text-red-700"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label
                                        class="block text-[10px] text-muted-foreground"
                                        >Tipo de Gráfico</label
                                    >
                                    <select
                                        v-model="s.chart_type"
                                        class="w-full rounded border p-1 text-xs dark:bg-neutral-800 dark:text-white"
                                    >
                                        <option value="line">Linha</option>
                                        <option value="bar">Barra</option>
                                        <option value="area">Área</option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="block text-[10px] text-muted-foreground"
                                        >Cor da Linha</label
                                    >
                                    <input
                                        v-model="s.color"
                                        type="color"
                                        class="h-7 w-full cursor-pointer rounded border p-0.5"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label
                                        class="block text-[10px] text-muted-foreground"
                                        >Espessura (stroke)</label
                                    >
                                    <input
                                        v-model.number="s.stroke_width"
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="w-full rounded border p-1 text-xs dark:bg-neutral-800 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-[10px] text-muted-foreground"
                                        >Posição Eixo Y</label
                                    >
                                    <select
                                        v-model="s.axis_position"
                                        class="w-full rounded border p-1 text-xs dark:bg-neutral-800 dark:text-white"
                                    >
                                        <option value="left">
                                            Esquerda (Left)
                                        </option>
                                        <option value="right">
                                            Direita (Right)
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <input
                                    v-model="s.independent_axis"
                                    type="checkbox"
                                    :id="`indep-${idx}`"
                                />
                                <label
                                    :for="`indep-${idx}`"
                                    class="text-[11px] text-slate-700 dark:text-neutral-300"
                                    >Eixo Y Independente</label
                                >
                            </div>

                            <div
                                v-if="s.independent_axis"
                                class="grid grid-cols-2 gap-2 pt-1"
                            >
                                <div>
                                    <label
                                        class="block text-[10px] text-muted-foreground"
                                        >Min Val</label
                                    >
                                    <input
                                        v-model.number="s.min_val"
                                        type="number"
                                        placeholder="Auto"
                                        class="w-full rounded border p-1 text-xs dark:bg-neutral-800 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-[10px] text-muted-foreground"
                                        >Max Val</label
                                    >
                                    <input
                                        v-model.number="s.max_val"
                                        type="number"
                                        placeholder="Auto"
                                        class="w-full rounded border p-1 text-xs dark:bg-neutral-800 dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="mt-6 flex items-center justify-end gap-2 border-t pt-3 dark:border-neutral-800"
            >
                <button
                    @click="emit('close')"
                    class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                >
                    Cancelar
                </button>
                <button
                    @click="handleSave"
                    class="rounded-md bg-primary px-4 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Salvar
                </button>
            </div>
        </div>
    </div>
</template>
