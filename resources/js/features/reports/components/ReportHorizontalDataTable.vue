<script setup lang="ts">
import { BarChart3, Check, LineChart, Tag } from '@lucide/vue';
import { ref } from 'vue';

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

interface AverageInfo {
    numeric: number | null;
    formatted: string;
}

const props = defineProps<{
    parameters: Parameter[];
    rows: Row[];
    averages?: Record<number, AverageInfo>;
}>();

const emit = defineEmits<{
    chart: [parameterId: number, type: 'line' | 'bar'];
}>();

const copiedParameterId = ref<number | null>(null);

function formatValue(
    value: number | null | undefined,
    decimals: number,
): string {
    if (value === undefined || value === null || Number.isNaN(value)) {
        return '—';
    }

    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function formatRowTime(row: Row): string {
    if (row.time) {
        return row.time;
    }

    if (row.timestamp) {
        const d = new Date(row.timestamp);

        if (!Number.isNaN(d.getTime())) {
            return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
        }
    }

    return row.timestamp;
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

function isOutOfLimits(
    value: number | null | undefined,
    parameter: Parameter,
): boolean {
    if (value === undefined || value === null || Number.isNaN(value)) {
        return false;
    }

    if (parameter.alert_1_min !== null && value < parameter.alert_1_min) {
        return true;
    }

    if (parameter.alert_1_max !== null && value > parameter.alert_1_max) {
        return true;
    }

    return false;
}

function getAverageFormatted(parameter: Parameter): string {
    if (props.averages && props.averages[parameter.id]) {
        return props.averages[parameter.id].formatted;
    }

    const validValues = props.rows
        .map((r) => r.values[parameter.id])
        .filter(
            (v): v is number =>
                v !== undefined && v !== null && !Number.isNaN(v),
        );

    if (validValues.length === 0) {
        return 'SR';
    }

    const sum = validValues.reduce((acc, val) => acc + val, 0);
    const avg = sum / validValues.length;

    return formatValue(avg, parameter.decimals);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            class="max-h-[550px] w-full overflow-x-auto overflow-y-auto rounded-xl border border-border bg-card shadow-2xs"
        >
            <table class="w-full min-w-max border-collapse text-[11px]">
                <thead class="sticky top-0 z-20 bg-muted/90 backdrop-blur-sm">
                    <tr class="border-b font-semibold text-foreground">
                        <!-- Coluna Fixa do Parâmetro -->
                        <th
                            class="sticky left-0 z-30 max-w-80 min-w-64 border-r bg-muted px-3 py-2 text-left font-semibold text-foreground"
                        >
                            <span class="text-xs font-semibold">Parâmetro</span>
                        </th>

                        <!-- Colunas Horizontais de Horários -->
                        <th
                            v-for="row in rows"
                            :key="row.timestamp"
                            class="min-w-16 border-r px-2 py-2 text-center text-xs font-semibold whitespace-nowrap text-foreground"
                        >
                            {{ formatRowTime(row) }}
                        </th>

                        <!-- Coluna de Média -->
                        <th
                            class="min-w-20 border-l border-border bg-muted/95 px-2.5 py-2 text-center text-xs font-bold text-foreground"
                        >
                            Média
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="parameter in parameters"
                        :key="parameter.id"
                        class="border-b border-border/40 transition hover:bg-muted/30"
                    >
                        <!-- Célula do Parâmetro (Sticky à Esquerda) -->
                        <td
                            class="sticky left-0 z-10 max-w-80 min-w-64 border-r bg-card px-3 py-2 text-left"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <strong
                                            class="truncate text-[12px] font-semibold text-foreground"
                                            :title="parameter.name"
                                        >
                                            {{ parameter.name }}
                                        </strong>
                                        <span
                                            v-if="parameter.unit"
                                            class="shrink-0 text-[10px] text-muted-foreground"
                                        >
                                            ({{ parameter.unit }})
                                        </span>
                                    </div>

                                    <div
                                        class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[10px] text-muted-foreground"
                                    >
                                        <span
                                            v-if="parameter.system_name"
                                            class="font-medium text-emerald-600 dark:text-emerald-400"
                                        >
                                            {{ parameter.system_name }} ·
                                        </span>
                                        <span
                                            v-if="
                                                parameter.alert_1_min !==
                                                    null ||
                                                parameter.alert_1_max !== null
                                            "
                                            class="py-0.2 rounded bg-muted/60 px-1"
                                            :title="`Faixa recomendada: ${parameter.alert_1_min ?? '—'} a ${parameter.alert_1_max ?? '—'}`"
                                        >
                                            Faixa:
                                            {{
                                                formatValue(
                                                    parameter.alert_1_min,
                                                    parameter.decimals,
                                                )
                                            }}
                                            -
                                            {{
                                                formatValue(
                                                    parameter.alert_1_max,
                                                    parameter.decimals,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Ações (Tag e Gráficos) -->
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        v-if="parameter.tag || parameter.code"
                                        type="button"
                                        class="inline-flex size-5 items-center justify-center rounded text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                        :title="`Tag: ${parameter.tag || parameter.code} (Clique para copiar)`"
                                        @click="copyTag(parameter)"
                                    >
                                        <Check
                                            v-if="
                                                copiedParameterId ===
                                                parameter.id
                                            "
                                            class="size-3 text-emerald-500"
                                        />
                                        <Tag v-else class="size-3" />
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex size-5 items-center justify-center rounded border bg-background text-foreground transition hover:bg-muted"
                                        title="Gráfico em linha"
                                        @click="
                                            emit('chart', parameter.id, 'line')
                                        "
                                    >
                                        <LineChart
                                            class="size-3 text-blue-600 dark:text-blue-400"
                                        />
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex size-5 items-center justify-center rounded border bg-background text-foreground transition hover:bg-muted"
                                        title="Gráfico em barras"
                                        @click="
                                            emit('chart', parameter.id, 'bar')
                                        "
                                    >
                                        <BarChart3
                                            class="size-3 text-indigo-600 dark:text-indigo-400"
                                        />
                                    </button>
                                </div>
                            </div>
                        </td>

                        <!-- Valores dos Horários na Horizontal -->
                        <td
                            v-for="row in rows"
                            :key="`${parameter.id}-${row.timestamp}`"
                            class="min-w-16 border-r px-2 py-1.5 text-center tabular-nums"
                            :class="[
                                isOutOfLimits(
                                    row.values[parameter.id],
                                    parameter,
                                )
                                    ? 'bg-amber-500/10 font-bold text-amber-700 dark:text-amber-400'
                                    : 'text-foreground',
                            ]"
                        >
                            {{
                                formatValue(
                                    row.values[parameter.id],
                                    parameter.decimals,
                                )
                            }}
                        </td>

                        <!-- Coluna Média -->
                        <td
                            class="min-w-20 border-l border-border bg-muted/20 px-2.5 py-1.5 text-center font-semibold text-foreground tabular-nums"
                        >
                            {{ getAverageFormatted(parameter) }}
                        </td>
                    </tr>

                    <tr v-if="parameters.length === 0">
                        <td
                            :colspan="rows.length + 2"
                            class="px-4 py-8 text-center text-xs text-muted-foreground"
                        >
                            Nenhum parâmetro monitorado configurado para este
                            sistema.
                        </td>
                    </tr>

                    <tr v-else-if="rows.length === 0">
                        <td
                            :colspan="rows.length + 2"
                            class="px-4 py-8 text-center text-xs text-muted-foreground"
                        >
                            Nenhuma medição registrada para a data de referência
                            deste relatório.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
