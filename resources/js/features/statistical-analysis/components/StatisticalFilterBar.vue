<script setup lang="ts">
import {
    Check,
    ChevronDown,
    Clock,
    Layers,
    Loader2,
    Play,
    Search,
    Sliders,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import type {
    ParameterItem,
    SystemItem,
} from '@/features/statistical-analysis/types';
import DatePicker from '@/shared/components/DatePicker.vue';

const selectedSystemId = defineModel<number | null>('selectedSystemId', {
    required: true,
});
const selectedParameterIds = defineModel<number[]>('selectedParameterIds', {
    required: true,
});
const startDate = defineModel<string>('startDate', { required: true });
const endDate = defineModel<string>('endDate', { required: true });
const lookbackValue = defineModel<number>('lookbackValue', { required: true });
const lookbackUnit = defineModel<'days' | 'samples'>('lookbackUnit', {
    required: true,
});
const frequency = defineModel<'raw' | 'daily_avg'>('frequency', {
    required: true,
});

const props = defineProps<{
    systems: SystemItem[];
    availableParameters: ParameterItem[];
    isLoading: boolean;
}>();

const emit = defineEmits<{
    load: [];
    openFactors: [];
}>();

// Parameter dropdown state
const isParamDropdownOpen = ref(false);
const paramSearch = ref('');
const paramDropdownRef = ref<HTMLDivElement | null>(null);

const filteredParameters = computed(() => {
    if (!paramSearch.value.trim()) {
        return props.availableParameters;
    }

    const q = paramSearch.value.toLowerCase().trim();

    return props.availableParameters.filter(
        (p) =>
            p.name.toLowerCase().includes(q) ||
            (p.code && p.code.toLowerCase().includes(q)),
    );
});

const isAllParamsSelected = computed(() => {
    return (
        props.availableParameters.length > 0 &&
        selectedParameterIds.value.length === props.availableParameters.length
    );
});

const paramDisplayLabel = computed(() => {
    const len = selectedParameterIds.value.length;

    if (len === 0) {
        return 'Selecione os parâmetros...';
    }

    if (len === 1) {
        const found = props.availableParameters.find(
            (p) => p.id === selectedParameterIds.value[0],
        );

        return found ? found.name : '1 parâmetro selecionado';
    }

    if (len === props.availableParameters.length) {
        return `Todos os parâmetros (${len})`;
    }

    return `${len} parâmetros selecionados`;
});

function toggleParameter(id: number) {
    if (selectedParameterIds.value.includes(id)) {
        selectedParameterIds.value = selectedParameterIds.value.filter(
            (pId) => pId !== id,
        );
    } else {
        selectedParameterIds.value = [...selectedParameterIds.value, id];
    }
}

function toggleSelectAllParams() {
    if (isAllParamsSelected.value) {
        selectedParameterIds.value = [];
    } else {
        selectedParameterIds.value = props.availableParameters.map((p) => p.id);
    }
}

function handleClickOutside(event: MouseEvent) {
    if (
        paramDropdownRef.value &&
        !paramDropdownRef.value.contains(event.target as Node)
    ) {
        isParamDropdownOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="space-y-3 rounded-xl border bg-card p-4 text-xs shadow-xs">
        <!-- Main Filter Row -->
        <div
            class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6"
        >
            <!-- 1. Sistema Select -->
            <div class="flex flex-col gap-1.5">
                <label
                    class="flex items-center gap-1.5 font-semibold text-foreground"
                >
                    <Layers class="h-3.5 w-3.5 text-primary" />
                    <span>Sistema</span>
                </label>
                <select
                    v-model.number="selectedSystemId"
                    class="flex h-9 w-full rounded-md border border-input bg-background px-2.5 py-1 text-xs font-medium text-foreground transition-colors focus:ring-2 focus:ring-ring focus:outline-none"
                >
                    <option
                        v-for="sys in systems"
                        :key="sys.id"
                        :value="sys.id"
                    >
                        {{ sys.name }}
                    </option>
                </select>
            </div>

            <!-- 2. Parâmetros Multi-select -->
            <div class="relative flex flex-col gap-1.5" ref="paramDropdownRef">
                <div class="flex items-center justify-between">
                    <label class="font-semibold text-foreground"
                        >Parâmetros</label
                    >
                    <span
                        v-if="selectedParameterIds.length > 0"
                        class="font-mono text-[10px] font-medium text-primary"
                    >
                        {{ selectedParameterIds.length }} selecionado(s)
                    </span>
                </div>

                <button
                    type="button"
                    @click="isParamDropdownOpen = !isParamDropdownOpen"
                    class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-background px-2.5 py-1 text-left text-xs font-medium text-foreground transition-colors hover:bg-muted/50 focus:ring-2 focus:ring-ring focus:outline-none"
                >
                    <span class="truncate">{{ paramDisplayLabel }}</span>
                    <ChevronDown class="ml-1 h-3.5 w-3.5 shrink-0 opacity-60" />
                </button>

                <!-- Dropdown Menu -->
                <div
                    v-if="isParamDropdownOpen"
                    class="absolute top-full left-0 z-50 mt-1 w-72 rounded-lg border bg-popover p-2 text-popover-foreground shadow-xl"
                >
                    <div
                        class="mb-2 flex items-center gap-1.5 rounded-md border bg-muted/40 px-2 py-1"
                    >
                        <Search class="h-3.5 w-3.5 text-muted-foreground" />
                        <input
                            v-model="paramSearch"
                            placeholder="Buscar parâmetro..."
                            class="w-full bg-transparent text-xs placeholder:text-muted-foreground focus:outline-none"
                        />
                    </div>

                    <div
                        class="mb-1 flex items-center justify-between border-b px-1 pb-1"
                    >
                        <button
                            type="button"
                            @click="toggleSelectAllParams"
                            class="text-[11px] font-semibold text-primary hover:underline"
                        >
                            {{
                                isAllParamsSelected
                                    ? 'Desmarcar todos'
                                    : 'Marcar todos'
                            }}
                        </button>
                        <span class="text-[11px] text-muted-foreground">
                            Total: {{ availableParameters.length }}
                        </span>
                    </div>

                    <div class="max-h-52 space-y-0.5 overflow-y-auto">
                        <div
                            v-for="param in filteredParameters"
                            :key="param.id"
                            @click="toggleParameter(param.id)"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs transition-colors',
                                selectedParameterIds.includes(param.id)
                                    ? 'bg-primary/10 font-medium text-primary'
                                    : 'text-foreground hover:bg-muted',
                            ]"
                        >
                            <div
                                :class="[
                                    'flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors',
                                    selectedParameterIds.includes(param.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-muted-foreground/30',
                                ]"
                            >
                                <Check
                                    v-if="
                                        selectedParameterIds.includes(param.id)
                                    "
                                    class="h-3 w-3"
                                />
                            </div>
                            <span class="flex-1 truncate">{{
                                param.name
                            }}</span>
                            <span
                                v-if="param.unit"
                                class="font-mono text-[10px] text-muted-foreground"
                            >
                                {{ param.unit }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Dados para trás (Baseline Lookback) -->
            <div class="flex flex-col gap-1.5">
                <label
                    class="flex items-center gap-1.5 font-semibold text-foreground"
                >
                    <Clock class="h-3.5 w-3.5 text-amber-500" />
                    <span>Dados para trás (Base)</span>
                </label>
                <div class="flex items-center gap-1">
                    <input
                        type="number"
                        min="1"
                        max="365"
                        v-model.number="lookbackValue"
                        class="flex h-9 w-16 rounded-md border border-input bg-background px-2 py-1 text-xs font-medium text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                    />
                    <select
                        v-model="lookbackUnit"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-2 py-1 text-xs font-medium text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="days">Dias</option>
                        <option value="samples">Amostras</option>
                    </select>
                </div>
            </div>

            <!-- 4. Data Inicial -->
            <div class="flex flex-col gap-1.5">
                <label class="font-semibold text-foreground"
                    >Data Inicial (Range)</label
                >
                <DatePicker v-model="startDate" placeholder="Data inicial" />
            </div>

            <!-- 5. Data Final -->
            <div class="flex flex-col gap-1.5">
                <label class="font-semibold text-foreground"
                    >Data Final (Range)</label
                >
                <DatePicker v-model="endDate" placeholder="Data final" />
            </div>

            <!-- 6. Frequência / Amostragem -->
            <div class="flex flex-col gap-1.5">
                <label class="font-semibold text-foreground">Amostragem</label>
                <select
                    v-model="frequency"
                    class="flex h-9 w-full rounded-md border border-input bg-background px-2.5 py-1 text-xs font-medium text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                >
                    <option value="raw">Por Leitura (Hora)</option>
                    <option value="daily_avg">Média Diária</option>
                </select>
            </div>
        </div>

        <!-- Action Buttons and Secondary Controls -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-t pt-2"
        >
            <button
                type="button"
                @click="emit('openFactors')"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border bg-muted/30 px-3 text-xs font-medium text-foreground shadow-2xs transition-colors hover:bg-muted"
            >
                <Sliders class="h-3.5 w-3.5 text-primary" />
                <span>Configurar Fatores & Alertas</span>
            </button>

            <button
                type="button"
                @click="emit('load')"
                :disabled="isLoading || selectedParameterIds.length === 0"
                class="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-xs font-semibold text-white shadow transition-all hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:opacity-50"
            >
                <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
                <Play v-else class="h-4 w-4 fill-white" />
                <span>Processar Análise</span>
            </button>
        </div>
    </div>
</template>
