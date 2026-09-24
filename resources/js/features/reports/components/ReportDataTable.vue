<script setup lang="ts">
import {
    BarChart3,
    Check,
    ChevronDown,
    LineChart,
    SlidersHorizontal,
    Tag,
} from '@lucide/vue';
import { computed, ref } from 'vue';

interface Parameter {
    id: number;
    name: string;
    code?: string | null;
    tag?: string | null;
    unit?: string | null;
    decimals: number;
    alert_1_min: number | null;
    alert_1_max: number | null;
    monitored_system_id?: number | null;
    system_name?: string | null;
}

interface Row {
    timestamp: string;
    time: string;
    date?: string;
    values: Record<number, number>;
}

const props = defineProps<{
    parameters: Parameter[];
    rows: Row[];
}>();

const emit = defineEmits<{
    chart: [parameterId: number, type: 'line' | 'bar'];
}>();

const copiedParameterId = ref<number | null>(null);
const hiddenParameterIds = ref<number[]>([]);
const isMenuOpen = ref(false);

const visibleParameters = computed(() =>
    props.parameters.filter(
        (parameter) => !hiddenParameterIds.value.includes(parameter.id),
    ),
);

function toggleParameter(id: number): void {
    if (hiddenParameterIds.value.includes(id)) {
        hiddenParameterIds.value = hiddenParameterIds.value.filter(
            (item) => item !== id,
        );
    } else {
        hiddenParameterIds.value.push(id);
    }
}

function showAllParameters(): void {
    hiddenParameterIds.value = [];
}

function hideAllParameters(): void {
    hiddenParameterIds.value = props.parameters.map((p) => p.id);
}

function formatValue(
    value: number | null | undefined,
    decimals: number,
): string {
    if (value === undefined || value === null || isNaN(value)) {
        return '';
    }

    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function formatRowDateTime(row: Row): string {
    if (row.timestamp) {
        const d = new Date(row.timestamp);

        if (!isNaN(d.getTime())) {
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            const time =
                row.time ||
                `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

            return `${day}/${month}/${year} ${time}`;
        }
    }

    return row.time || row.timestamp;
}

function copyTag(parameter: Parameter): void {
    const tagToCopy = parameter.tag || parameter.code || '';

    if (!tagToCopy) {
        return;
    }

    void navigator.clipboard.writeText(tagToCopy);
    copiedParameterId.value = parameter.id;
    setTimeout(() => {
        if (copiedParameterId.value === parameter.id) {
            copiedParameterId.value = null;
        }
    }, 1500);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-if="parameters.length > 0"
            class="flex items-center justify-between gap-2 px-1 text-xs"
        >
            <div class="flex items-center gap-2 text-muted-foreground">
                <span>
                    Parâmetros:
                    <strong class="font-semibold text-foreground">
                        {{ visibleParameters.length }}/{{ parameters.length }}
                    </strong>
                </span>
            </div>

            <div class="relative">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-medium text-foreground shadow-2xs transition hover:bg-muted focus:outline-hidden"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <SlidersHorizontal class="size-3.5 text-muted-foreground" />
                    <span>Colunas</span>
                    <ChevronDown class="size-3 text-muted-foreground" />
                </button>

                <div
                    v-if="isMenuOpen"
                    class="absolute right-0 z-50 mt-1.5 w-64 rounded-xl border border-border bg-popover p-2 shadow-lg ring-1 ring-black/5 dark:ring-white/10"
                >
                    <div
                        class="mb-2 flex items-center justify-between border-b pb-1.5 text-xs"
                    >
                        <span class="font-medium text-popover-foreground">
                            Exibir / Ocultar Parâmetros
                        </span>
                        <div class="flex gap-1.5">
                            <button
                                type="button"
                                class="text-[11px] text-emerald-600 hover:underline dark:text-emerald-400"
                                @click="showAllParameters"
                            >
                                Todos
                            </button>
                            <span class="text-muted-foreground">|</span>
                            <button
                                type="button"
                                class="text-[11px] text-muted-foreground hover:underline"
                                @click="hideAllParameters"
                            >
                                Nenhum
                            </button>
                        </div>
                    </div>

                    <div class="max-h-56 space-y-1 overflow-y-auto pr-1">
                        <div
                            v-for="parameter in parameters"
                            :key="parameter.id"
                            class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-xs text-popover-foreground hover:bg-muted"
                        >
                            <div
                                class="flex min-w-0 flex-1 items-center gap-1.5"
                            >
                                <span class="truncate font-medium">
                                    {{ parameter.name }}
                                </span>
                                <span
                                    v-if="parameter.system_name"
                                    class="shrink-0 text-[10px] font-normal text-muted-foreground"
                                >
                                    ({{ parameter.system_name }})
                                </span>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="
                                    !hiddenParameterIds.includes(parameter.id)
                                "
                                class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                                :class="
                                    !hiddenParameterIds.includes(parameter.id)
                                        ? 'bg-emerald-600'
                                        : 'bg-muted-foreground/30'
                                "
                                @click="toggleParameter(parameter.id)"
                            >
                                <span
                                    aria-hidden="true"
                                    class="pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out"
                                    :class="
                                        !hiddenParameterIds.includes(
                                            parameter.id,
                                        )
                                            ? 'translate-x-3'
                                            : 'translate-x-0'
                                    "
                                />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="max-h-[550px] w-full overflow-x-auto overflow-y-auto rounded-xl border border-border bg-card shadow-2xs"
        >
            <table class="w-full min-w-max border-collapse text-[11px]">
                <thead class="sticky top-0 z-20 bg-muted/90 backdrop-blur-sm">
                    <tr class="border-b font-semibold text-foreground">
                        <th
                            class="sticky left-0 z-30 w-28 min-w-28 border-r bg-muted px-1.5 py-1 text-center font-semibold whitespace-nowrap text-foreground"
                        >
                            Data / Hora
                        </th>

                        <th
                            v-for="parameter in visibleParameters"
                            :key="parameter.id"
                            class="max-w-32 min-w-20 border-r px-1.5 py-1 text-center align-top"
                        >
                            <div class="flex flex-col items-center gap-0.5">
                                <!-- Line 1: Parameter name -->
                                <span
                                    class="w-full truncate text-[11px] font-semibold text-foreground"
                                    :title="parameter.name"
                                >
                                    {{ parameter.name }}
                                </span>

                                <!-- Line 1.5: System name (if available) -->
                                <span
                                    v-if="parameter.system_name"
                                    class="w-full truncate text-[9px] font-medium text-emerald-600 dark:text-emerald-400"
                                    :title="`Sistema: ${parameter.system_name}`"
                                >
                                    {{ parameter.system_name }}
                                </span>

                                <!-- Line 2: Unit (if exists) -->
                                <span
                                    v-if="parameter.unit"
                                    class="w-full truncate text-[10px] font-normal text-muted-foreground"
                                    :title="`Unidade: ${parameter.unit}`"
                                >
                                    ({{ parameter.unit }})
                                </span>

                                <!-- Line 3: Action Icons -->
                                <div
                                    class="mt-0.5 flex items-center justify-center gap-0.5"
                                >
                                    <button
                                        v-if="parameter.tag || parameter.code"
                                        type="button"
                                        class="inline-flex size-4 items-center justify-center rounded text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                        :title="`Tag: ${parameter.tag || parameter.code} (Clique para copiar)`"
                                        @click="copyTag(parameter)"
                                    >
                                        <Check
                                            v-if="
                                                copiedParameterId ===
                                                parameter.id
                                            "
                                            class="size-2.5 text-emerald-500"
                                        />
                                        <Tag v-else class="size-2.5" />
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex size-4 items-center justify-center rounded border bg-background text-foreground transition hover:bg-muted"
                                        title="Gráfico em linha"
                                        @click="
                                            emit('chart', parameter.id, 'line')
                                        "
                                    >
                                        <LineChart class="size-2.5" />
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex size-4 items-center justify-center rounded border bg-background text-foreground transition hover:bg-muted"
                                        title="Gráfico em barras"
                                        @click="
                                            emit('chart', parameter.id, 'bar')
                                        "
                                    >
                                        <BarChart3 class="size-2.5" />
                                    </button>
                                </div>

                                <!-- Line 4: Limits -->
                                <div
                                    class="w-full truncate text-[9px] font-normal text-muted-foreground"
                                    :title="`Limites: ${
                                        parameter.alert_1_min !== null
                                            ? formatValue(
                                                  parameter.alert_1_min,
                                                  parameter.decimals,
                                              )
                                            : '—'
                                    } a ${
                                        parameter.alert_1_max !== null
                                            ? formatValue(
                                                  parameter.alert_1_max,
                                                  parameter.decimals,
                                              )
                                            : '—'
                                    }`"
                                >
                                    <template
                                        v-if="
                                            parameter.alert_1_min !== null ||
                                            parameter.alert_1_max !== null
                                        "
                                    >
                                        {{
                                            parameter.alert_1_min !== null
                                                ? formatValue(
                                                      parameter.alert_1_min,
                                                      parameter.decimals,
                                                  )
                                                : '—'
                                        }}
                                        -
                                        {{
                                            parameter.alert_1_max !== null
                                                ? formatValue(
                                                      parameter.alert_1_max,
                                                      parameter.decimals,
                                                  )
                                                : '—'
                                        }}
                                    </template>
                                    <template v-else> — </template>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.timestamp"
                        class="border-b border-border/40 transition hover:bg-muted/30"
                    >
                        <td
                            class="sticky left-0 z-10 w-28 min-w-28 border-r bg-card px-1.5 py-1 text-center font-medium whitespace-nowrap text-foreground"
                        >
                            {{ formatRowDateTime(row) }}
                        </td>

                        <td
                            v-for="parameter in visibleParameters"
                            :key="`${row.timestamp}-${parameter.id}`"
                            class="max-w-32 min-w-20 truncate border-r px-1.5 py-1 text-center text-foreground tabular-nums"
                        >
                            {{
                                formatValue(
                                    row.values[parameter.id],
                                    parameter.decimals,
                                )
                            }}
                        </td>
                    </tr>

                    <tr v-if="rows.length === 0">
                        <td
                            :colspan="visibleParameters.length + 1"
                            class="px-4 py-6 text-center text-xs text-muted-foreground"
                        >
                            Nenhum registro encontrado no período selecionado.
                        </td>
                    </tr>

                    <tr v-if="visibleParameters.length === 0">
                        <td
                            class="px-4 py-6 text-center text-xs text-muted-foreground"
                        >
                            Nenhum parâmetro selecionado ou ativo.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
