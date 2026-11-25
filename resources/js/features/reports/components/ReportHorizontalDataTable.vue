<script setup lang="ts">
import {
    BarChart3,
    Check,
    LineChart,
    MessageSquareText,
    Tag,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ReportGroupedSystemsFilter from '@/features/reports/components/ReportGroupedSystemsFilter.vue';
import ReportRowObservationsModal from '@/features/reports/components/ReportRowObservationsModal.vue';
import {
    formatLimitsLabel,
    formatNumericValue,
    formatReportHour,
    getCategoryMeta,
    hasLimits,
    isValueOutsideLimits,
} from '@/features/reports/reportMetrics';
import type {
    ParameterCategoryCode,
    ReportTableParameter,
    ReportTableRow,
    RowObservation,
} from '@/features/reports/types';

interface AverageInfo {
    numeric: number | null;
    formatted: string;
}

const props = withDefaults(
    defineProps<{
        parameters: ReportTableParameter[];
        rows: ReportTableRow[];
        averages?: Record<number, AverageInfo>;
        visibleCategories?: Record<ParameterCategoryCode, boolean>;
        showTags?: boolean;
        showMean?: boolean;
        showCharts?: boolean;
        showSystemColumn?: boolean;
        tableFontSize?: number;
        hideGroupedFilter?: boolean;
    }>(),
    {
        averages: undefined,
        visibleCategories: undefined,
        showTags: true,
        showMean: true,
        showCharts: true,
        showSystemColumn: false,
        tableFontSize: 13,
        hideGroupedFilter: false,
    },
);

const emit = defineEmits<{
    chart: [parameterId: number, type: 'line' | 'bar'];
}>();

const copiedTagKey = ref<string | null>(null);
const disabledSystemIds = ref<number[]>([]);

const obsModal = ref<{
    open: boolean;
    dateLabel: string;
    timeLabel: string;
    observations: RowObservation[];
}>({
    open: false,
    dateLabel: '',
    timeLabel: '',
    observations: [],
});

const visibleParameters = computed(() => {
    return props.parameters.filter((parameter) => {
        if (
            disabledSystemIds.value.length > 0 &&
            parameter.monitored_system_id &&
            disabledSystemIds.value.includes(parameter.monitored_system_id)
        ) {
            return false;
        }

        if (props.visibleCategories && parameter.category) {
            const code =
                parameter.category.toUpperCase() as ParameterCategoryCode;

            if (code in props.visibleCategories) {
                return props.visibleCategories[code];
            }
        }

        return true;
    });
});

function formatRowTime(row: ReportTableRow): string {
    if (row.time) {
        return formatReportHour(row.time);
    }

    if (row.timestamp) {
        const d = new Date(row.timestamp);

        if (!Number.isNaN(d.getTime())) {
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');

            return `${hh}h${mm}`;
        }
    }

    return row.timestamp;
}

function copyTag(parameter: ReportTableParameter): void {
    const tagToCopy = parameter.tag || parameter.code || '';

    if (!tagToCopy) {
        return;
    }

    void navigator.clipboard.writeText(tagToCopy);
    const key = String(parameter.id);
    copiedTagKey.value = key;
    setTimeout(() => {
        if (copiedTagKey.value === key) {
            copiedTagKey.value = null;
        }
    }, 1500);
}

function getAverageFormatted(parameter: ReportTableParameter): string {
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
        return '—';
    }

    const sum = validValues.reduce((acc, val) => acc + val, 0);

    return formatNumericValue(sum / validValues.length, parameter.decimals);
}

function isParameterGroupStart(index: number): boolean {
    if (!props.showSystemColumn || index === 0) {
        return false;
    }

    const currentName = visibleParameters.value[index].name
        .trim()
        .toLowerCase();
    const prevName = visibleParameters.value[index - 1].name
        .trim()
        .toLowerCase();

    return currentName !== prevName;
}

function openObservations(row: ReportTableRow): void {
    obsModal.value = {
        open: true,
        dateLabel: row.date || row.timestamp.slice(0, 10),
        timeLabel: row.time || formatRowTime(row),
        observations: row.comments ?? [],
    };
}

const totalColumnsCount = computed(
    () =>
        1 +
        (props.showSystemColumn ? 1 : 0) +
        2 +
        (props.showCharts ? 1 : 0) +
        props.rows.length +
        (props.showMean ? 1 : 0),
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <ReportGroupedSystemsFilter
            v-if="!hideGroupedFilter"
            :parameters="parameters"
            v-model:disabled-system-ids="disabledSystemIds"
            :bordered="true"
        />

        <div
            class="max-h-[560px] w-full overflow-auto rounded-lg border border-border bg-card shadow-2xs"
        >
            <table
                class="w-full min-w-max border-separate border-spacing-0 tabular-nums"
                :style="{ fontSize: `${tableFontSize}px` }"
            >
                <thead class="sticky top-0 z-20 bg-muted/95 backdrop-blur-sm">
                    <tr class="text-[11px] font-semibold text-muted-foreground">
                        <th
                            class="sticky left-0 z-30 min-w-52 border-r border-b border-border bg-muted px-3 py-2 text-left text-foreground"
                        >
                            Parâmetro
                        </th>

                        <th
                            v-if="showSystemColumn"
                            class="min-w-36 border-r border-b border-border bg-muted px-2.5 py-2 text-left text-foreground"
                        >
                            Sistema
                        </th>

                        <th
                            class="w-16 border-r border-b border-border px-2 py-2 text-center"
                        >
                            Tipo
                        </th>

                        <th
                            class="min-w-24 border-r border-b border-border px-2 py-2 text-center"
                        >
                            Limites
                        </th>

                        <th
                            v-for="row in rows"
                            :key="row.timestamp"
                            class="min-w-16 border-r border-b border-border px-2 py-2 text-center whitespace-nowrap text-foreground"
                        >
                            <span
                                class="inline-flex items-center justify-center gap-1"
                            >
                                <span>{{ formatRowTime(row) }}</span>
                                <button
                                    v-if="row.has_comments"
                                    type="button"
                                    title="Consultar observações deste horário"
                                    aria-label="Consultar observações deste horário"
                                    class="inline-flex size-4 items-center justify-center rounded text-primary transition hover:bg-primary/15"
                                    @click="openObservations(row)"
                                >
                                    <MessageSquareText class="size-3" />
                                </button>
                            </span>
                        </th>

                        <th
                            v-if="showMean"
                            class="min-w-20 border-r border-b border-border bg-muted/95 px-2.5 py-2 text-center font-bold text-foreground"
                        >
                            Média
                        </th>

                        <th
                            v-if="showCharts"
                            class="w-16 border-b border-border px-2 py-2 text-center"
                        >
                            Gráfico
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="(parameter, index) in visibleParameters"
                        :key="parameter.id"
                        class="transition hover:bg-[var(--report-hover)]"
                        :class="
                            isParameterGroupStart(index)
                                ? '[&>td]:border-t-2 [&>td]:border-t-border'
                                : ''
                        "
                    >
                        <!-- Parâmetro + Unidade + TAG -->
                        <td
                            class="sticky left-0 z-10 min-w-52 border-r border-b border-border/70 bg-card px-3 py-2 text-left"
                        >
                            <div class="flex items-baseline gap-1.5">
                                <span
                                    class="font-semibold text-foreground"
                                    :title="parameter.name"
                                >
                                    {{ parameter.name }}
                                </span>
                                <span
                                    v-if="parameter.unit"
                                    class="text-[0.85em] font-medium text-muted-foreground"
                                >
                                    {{ parameter.unit }}
                                </span>
                            </div>

                            <div
                                v-if="showTags"
                                class="mt-0.5 flex items-center gap-1 text-[10.5px] text-muted-foreground"
                            >
                                <span class="font-semibold">TAG:</span>
                                <span class="truncate">
                                    {{ parameter.tag || parameter.code || '–' }}
                                </span>
                                <button
                                    v-if="parameter.tag || parameter.code"
                                    type="button"
                                    class="inline-flex size-4 shrink-0 items-center justify-center rounded text-primary transition hover:bg-primary/10"
                                    :title="`Copiar TAG: ${parameter.tag || parameter.code}`"
                                    @click="copyTag(parameter)"
                                >
                                    <Check
                                        v-if="
                                            copiedTagKey ===
                                            String(parameter.id)
                                        "
                                        class="size-2.5 text-emerald-600"
                                    />
                                    <Tag v-else class="size-2.5" />
                                </button>
                            </div>
                        </td>

                        <!-- Sistema (somente no modo comparativo Por parâmetro) -->
                        <td
                            v-if="showSystemColumn"
                            class="border-r border-b border-border/70 px-2.5 py-2 text-left text-[11px] font-medium whitespace-nowrap text-muted-foreground"
                        >
                            {{ parameter.system_name || '—' }}
                        </td>

                        <!-- Tipo -->
                        <td
                            class="border-r border-b border-border/70 px-2 py-2 text-center"
                        >
                            <span
                                v-if="getCategoryMeta(parameter.category)"
                                class="inline-flex min-w-9 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                :class="
                                    getCategoryMeta(parameter.category)
                                        ?.badgeClass
                                "
                            >
                                {{ getCategoryMeta(parameter.category)?.code }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">
                                —
                            </span>
                        </td>

                        <!-- Limites -->
                        <td
                            class="border-r border-b border-border/70 px-2 py-2 text-center"
                        >
                            <span
                                v-if="hasLimits(parameter)"
                                class="inline-block rounded-full bg-[var(--report-limit-bg)] px-2 py-0.5 text-[10.5px] font-semibold whitespace-nowrap text-[var(--report-limit-ink)]"
                            >
                                {{ formatLimitsLabel(parameter) }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">
                                —
                            </span>
                        </td>

                        <!-- Medições dos Horários -->
                        <td
                            v-for="row in rows"
                            :key="`${parameter.id}-${row.timestamp}`"
                            class="min-w-16 border-r border-b border-border/70 px-2 py-2 text-center"
                            :class="
                                isValueOutsideLimits(
                                    row.values[parameter.id],
                                    parameter,
                                )
                                    ? 'font-bold text-[#c52c32] dark:text-[#ff8588]'
                                    : row.values[parameter.id] === undefined ||
                                        row.values[parameter.id] === null
                                      ? 'text-muted-foreground'
                                      : 'text-foreground'
                            "
                        >
                            {{
                                formatNumericValue(
                                    row.values[parameter.id],
                                    parameter.decimals,
                                )
                            }}
                        </td>

                        <!-- Média -->
                        <td
                            v-if="showMean"
                            class="min-w-20 border-r border-b border-border/70 bg-[var(--report-mean)] px-2.5 py-2 text-center font-semibold text-foreground"
                        >
                            {{ getAverageFormatted(parameter) }}
                        </td>

                        <!-- Gráficos -->
                        <td
                            v-if="showCharts"
                            class="border-b border-border/70 px-2 py-2 text-center"
                        >
                            <div class="flex items-center justify-center gap-1">
                                <button
                                    type="button"
                                    class="inline-flex size-6 items-center justify-center rounded text-muted-foreground transition hover:bg-primary/10 hover:text-primary"
                                    title="Gráfico de tendência"
                                    @click="emit('chart', parameter.id, 'line')"
                                >
                                    <LineChart class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex size-6 items-center justify-center rounded text-muted-foreground transition hover:bg-primary/10 hover:text-primary"
                                    title="Gráfico em colunas"
                                    @click="emit('chart', parameter.id, 'bar')"
                                >
                                    <BarChart3 class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="parameters.length === 0">
                        <td
                            :colspan="totalColumnsCount"
                            class="px-4 py-8 text-center text-xs text-muted-foreground"
                        >
                            Nenhum parâmetro monitorado configurado para este
                            sistema.
                        </td>
                    </tr>

                    <tr v-else-if="visibleParameters.length === 0">
                        <td
                            :colspan="totalColumnsCount"
                            class="px-4 py-10 text-center text-xs text-muted-foreground"
                        >
                            Nenhum parâmetro exibido com os filtros atuais.
                        </td>
                    </tr>

                    <tr v-else-if="rows.length === 0">
                        <td
                            :colspan="totalColumnsCount"
                            class="px-4 py-8 text-center text-xs text-muted-foreground"
                        >
                            Nenhuma medição registrada para a data de referência
                            deste relatório.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ReportRowObservationsModal
            :open="obsModal.open"
            :date-label="obsModal.dateLabel"
            :time-label="obsModal.timeLabel"
            :observations="obsModal.observations"
            @close="obsModal.open = false"
        />
    </div>
</template>
