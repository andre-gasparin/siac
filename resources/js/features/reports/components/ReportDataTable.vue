<script setup lang="ts">
import {
    BarChart3,
    Check,
    LineChart,
    MessageSquareText,
    SlidersHorizontal,
    Tag,
} from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, onMounted, ref } from 'vue';
import ReportRowObservationsModal from '@/features/reports/components/ReportRowObservationsModal.vue';
import {
    formatDateBr,
    formatLimitsLabel,
    formatNumericValue,
    formatReportHour,
    getCategoryMeta,
    hasLimits,
    isValueOutsideLimits,
} from '@/features/reports/reportMetrics';
import type {
    ReportTableParameter,
    ReportTableRow,
    RowObservation,
} from '@/features/reports/types';

interface SystemInfo {
    id: number;
    name: string;
}

interface SystemTableSection {
    id: number;
    name: string;
    parameters: ReportTableParameter[];
    visibleParameters: ReportTableParameter[];
    rows: ReportTableRow[];
}

const HEIGHT_STORAGE_KEY = 'consucal-history-table-height';

const props = withDefaults(
    defineProps<{
        parameters: ReportTableParameter[];
        rows: ReportTableRow[];
        averages?: Record<
            number,
            { numeric: number | null; formatted: string }
        >;
        startDate?: string;
        endDate?: string;
        reportDateReference?: string;
        isLoadingData?: boolean;
        primarySystem?: SystemInfo | null;
        groupedChildren?: SystemInfo[];
    }>(),
    {
        averages: undefined,
        startDate: '',
        endDate: '',
        reportDateReference: '',
        isLoadingData: false,
        primarySystem: null,
        groupedChildren: () => [],
    },
);

const emit = defineEmits<{
    chart: [parameterId: number, type: 'line' | 'bar'];
    'update:startDate': [value: string];
    'update:endDate': [value: string];
    loadData: [dates?: { start_date: string; end_date: string }];
}>();

const copiedParameterId = ref<number | null>(null);
const hiddenParameterIds = ref<number[]>([]);
const isMenuOpen = ref(false);
const columnsMenuRef = ref<HTMLElement | null>(null);
const tableHeight = ref(450);

onClickOutside(columnsMenuRef, () => {
    isMenuOpen.value = false;
});

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

onMounted(() => {
    try {
        const stored = Number(
            sessionStorage.getItem(HEIGHT_STORAGE_KEY) || '450',
        );

        if (!Number.isNaN(stored) && stored >= 200 && stored <= 900) {
            tableHeight.value = stored;
        }
    } catch {
        // Ignore storage errors
    }
});

function setTableHeight(value: number): void {
    const clamped = Math.max(200, Math.min(900, Math.round(value)));
    tableHeight.value = clamped;

    try {
        sessionStorage.setItem(HEIGHT_STORAGE_KEY, String(clamped));
    } catch {
        // Ignore storage errors
    }
}

const periodDaysCount = computed(() => {
    if (!props.startDate || !props.endDate) {
        return null;
    }

    const start = new Date(`${props.startDate}T12:00:00`);
    const end = new Date(`${props.endDate}T12:00:00`);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return null;
    }

    return Math.max(
        1,
        Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) +
            1,
    );
});

const visibleParameters = computed(() =>
    props.parameters.filter(
        (parameter) => !hiddenParameterIds.value.includes(parameter.id),
    ),
);

const systemTableSections = computed<SystemTableSection[]>(() => {
    const orderedSystems: SystemInfo[] = [];
    const seenIds = new Set<number>();

    if (props.primarySystem) {
        orderedSystems.push(props.primarySystem);
        seenIds.add(props.primarySystem.id);
    }

    for (const child of props.groupedChildren) {
        if (!seenIds.has(child.id)) {
            orderedSystems.push(child);
            seenIds.add(child.id);
        }
    }

    for (const parameter of props.parameters) {
        const sid =
            parameter.monitored_system_id ?? props.primarySystem?.id ?? 0;

        if (!seenIds.has(sid)) {
            orderedSystems.push({
                id: sid,
                name: parameter.system_name || `Sistema #${sid}`,
            });
            seenIds.add(sid);
        }
    }

    if (orderedSystems.length === 0) {
        return [];
    }

    const isMultiSystem = orderedSystems.length > 1;

    return orderedSystems.map((sys) => {
        const sysParameters = isMultiSystem
            ? props.parameters.filter(
                  (parameter) =>
                      (parameter.monitored_system_id ??
                          props.primarySystem?.id ??
                          0) === sys.id,
              )
            : props.parameters;

        const sysVisible = sysParameters.filter(
            (parameter) => !hiddenParameterIds.value.includes(parameter.id),
        );

        const sysRows: ReportTableRow[] = isMultiSystem
            ? props.rows
                  .map((row): ReportTableRow | null => {
                      const sysComments = (row.comments ?? []).filter(
                          (comment) => comment.system_id === sys.id,
                      );
                      const hasValue = sysParameters.some((param) => {
                          const val = row.values[param.id];

                          return (
                              val !== undefined &&
                              val !== null &&
                              !Number.isNaN(val)
                          );
                      });

                      if (!hasValue && sysComments.length === 0) {
                          return null;
                      }

                      return {
                          ...row,
                          has_comments: sysComments.length > 0,
                          comments: sysComments,
                      };
                  })
                  .filter((row): row is ReportTableRow => row !== null)
            : props.rows;

        return {
            id: sys.id,
            name: sys.name,
            parameters: sysParameters,
            visibleParameters: sysVisible,
            rows: sysRows,
        };
    });
});

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

function getRowDateIso(row: ReportTableRow): string {
    if (row.timestamp && /^\d{4}-\d{2}-\d{2}/.test(row.timestamp)) {
        return row.timestamp.slice(0, 10);
    }

    return '';
}

function getRowDateBr(row: ReportTableRow): string {
    if (row.date) {
        return row.date;
    }

    const iso = getRowDateIso(row);

    return iso ? formatDateBr(iso) : '—';
}

function getRowTimeLabel(row: ReportTableRow): string {
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

function isReferenceRow(row: ReportTableRow): boolean {
    if (!props.reportDateReference) {
        return false;
    }

    return getRowDateIso(row) === props.reportDateReference.slice(0, 10);
}

function isDayBreak(sectionRows: ReportTableRow[], index: number): boolean {
    if (index === 0) {
        return false;
    }

    return (
        getRowDateBr(sectionRows[index]) !==
        getRowDateBr(sectionRows[index - 1])
    );
}

function getColumnAverage(
    parameter: ReportTableParameter,
    sectionRows: ReportTableRow[],
): string {
    if (
        props.averages &&
        props.averages[parameter.id] &&
        periodDaysCount.value === 1
    ) {
        return props.averages[parameter.id].formatted;
    }

    const validValues = sectionRows
        .map((r) => r.values[parameter.id])
        .filter(
            (v): v is number =>
                v !== undefined && v !== null && !Number.isNaN(v),
        );

    if (validValues.length === 0) {
        return '—';
    }

    const sum = validValues.reduce((acc, v) => acc + v, 0);

    return formatNumericValue(sum / validValues.length, parameter.decimals);
}

function copyTag(parameter: ReportTableParameter): void {
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

function openObservations(row: ReportTableRow): void {
    obsModal.value = {
        open: true,
        dateLabel: getRowDateBr(row),
        timeLabel: row.time || getRowTimeLabel(row),
        observations: row.comments ?? [],
    };
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-2.5">
        <!-- Barra Compacta de Controles Históricos (uma linha: período/data à esquerda, altura/colunas à direita) -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b border-border/80 pb-2 text-xs lg:flex-nowrap"
        >
            <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1">
                <div
                    v-if="startDate && endDate"
                    class="flex flex-wrap items-center gap-1.5 text-[11px] whitespace-nowrap text-muted-foreground"
                >
                    <strong class="text-foreground">Período:</strong>
                    <span class="font-medium text-foreground">
                        {{ formatDateBr(startDate) }} a
                        {{ formatDateBr(endDate) }}
                    </span>
                    <span v-if="periodDaysCount" class="text-muted-foreground">
                        ({{ periodDaysCount }}
                        {{ periodDaysCount === 1 ? 'dia' : 'dias' }})
                    </span>
                </div>

                <div
                    v-if="reportDateReference"
                    class="flex items-center gap-1.5 text-[11px] whitespace-nowrap"
                >
                    <strong class="text-foreground">Data do relatório:</strong>
                    <span
                        class="inline-flex items-center rounded-full bg-[#e5f0fd] px-2 py-0.5 text-[10.5px] font-bold text-[#235d91] dark:bg-[#263f58] dark:text-[#b6daf6]"
                    >
                        {{ formatDateBr(reportDateReference) }}
                    </span>
                </div>
            </div>

            <!-- Controles de Altura + Colunas -->
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <div
                    role="group"
                    aria-label="Altura da tabela histórica"
                    class="flex flex-wrap items-center gap-1 text-[11px]"
                >
                    <span class="mr-0.5 font-semibold text-muted-foreground">
                        Altura:
                    </span>
                    <button
                        v-for="preset in [
                            { h: 300, label: 'Compacta' },
                            { h: 450, label: 'Média' },
                            { h: 680, label: 'Ampla' },
                        ]"
                        :key="preset.h"
                        type="button"
                        :aria-pressed="tableHeight === preset.h"
                        class="rounded border px-2 py-0.5 text-[11px] transition"
                        :class="
                            tableHeight === preset.h
                                ? 'border-[#a8c9dd] bg-[#e5f0f8] font-bold text-[#205b82] dark:border-[#5989a5] dark:bg-[#25465c] dark:text-[#cdeaff]'
                                : 'border-border bg-card text-foreground hover:bg-muted'
                        "
                        @click="setTableHeight(preset.h)"
                    >
                        {{ preset.label }}
                    </button>

                    <div class="hidden items-center gap-1.5 pl-1 sm:flex">
                        <span class="text-muted-foreground">Ajustar:</span>
                        <input
                            :value="tableHeight"
                            type="range"
                            min="200"
                            max="900"
                            step="10"
                            aria-label="Redimensionar altura da tabela histórica"
                            class="w-20 cursor-pointer accent-primary"
                            @input="
                                setTableHeight(
                                    Number(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    ),
                                )
                            "
                        />
                        <output
                            class="min-w-10 text-[10px] font-medium text-muted-foreground tabular-nums"
                        >
                            {{ tableHeight }} px
                        </output>
                    </div>
                </div>

                <!-- Menu Colunas -->
                <div
                    v-if="parameters.length > 0"
                    ref="columnsMenuRef"
                    class="relative"
                >
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-border bg-card p-1.5 text-foreground shadow-2xs transition hover:bg-muted focus:outline-hidden"
                        title="Configurar Colunas"
                        aria-label="Configurar Colunas"
                        @click="isMenuOpen = !isMenuOpen"
                    >
                        <SlidersHorizontal
                            class="size-3.5 text-muted-foreground"
                        />
                    </button>

                    <div
                        v-if="isMenuOpen"
                        class="absolute right-0 z-50 mt-1.5 w-64 rounded-lg border border-border bg-popover p-2 shadow-lg ring-1 ring-black/5 dark:ring-white/10"
                    >
                        <div
                            class="mb-2 flex items-center justify-between border-b pb-1.5 text-xs"
                        >
                            <span class="font-medium text-popover-foreground">
                                Colunas ({{ visibleParameters.length }}/{{
                                    parameters.length
                                }})
                            </span>
                            <div class="flex gap-1.5">
                                <button
                                    type="button"
                                    class="text-[11px] text-primary hover:underline"
                                    @click="showAllParameters"
                                >
                                    Todas
                                </button>
                                <span class="text-muted-foreground">|</span>
                                <button
                                    type="button"
                                    class="text-[11px] text-muted-foreground hover:underline"
                                    @click="hideAllParameters"
                                >
                                    Nenhuma
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
                                        v-if="
                                            systemTableSections.length > 1 &&
                                            parameter.system_name
                                        "
                                        class="shrink-0 text-[10px] font-normal text-muted-foreground"
                                    >
                                        ({{ parameter.system_name }})
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="
                                        !hiddenParameterIds.includes(
                                            parameter.id,
                                        )
                                    "
                                    class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                                    :class="
                                        !hiddenParameterIds.includes(
                                            parameter.id,
                                        )
                                            ? 'bg-primary'
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
        </div>

        <!-- Tabelas empilhadas por sistema -->
        <div
            v-for="sysSection in systemTableSections"
            :key="sysSection.id"
            data-test="history-system-section"
            class="space-y-1.5 pt-1"
        >
            <div class="flex flex-wrap items-baseline gap-1.5 px-0.5">
                <strong class="text-xs font-semibold text-foreground">
                    {{ sysSection.name }}
                </strong>
                <span class="text-[10.5px] text-muted-foreground">
                    · Consulta dos resultados cadastrados
                </span>
            </div>

            <div
                v-if="sysSection.parameters.length === 0"
                class="rounded-xl border border-dashed border-border/80 bg-muted/15 px-4 py-8 text-center text-xs text-muted-foreground"
            >
                {{ sysSection.name }}: Sem parâmetros cadastrados.
            </div>

            <div
                v-else-if="sysSection.visibleParameters.length === 0"
                class="rounded-xl border border-dashed border-border/80 bg-muted/15 px-4 py-8 text-center text-xs text-muted-foreground"
            >
                Nenhum parâmetro selecionado no menu de colunas.
            </div>

            <!-- Único Contêiner de Rolagem Vertical + Horizontal -->
            <div
                v-else
                data-test="report-data-table-scroll"
                :style="{ height: `${tableHeight}px` }"
                class="min-h-[190px] w-full max-w-full min-w-0 resize-y overflow-auto rounded-xl border border-border bg-card shadow-2xs"
            >
                <table
                    class="w-full min-w-max border-separate border-spacing-0 text-[11px] tabular-nums"
                >
                    <thead
                        class="sticky top-0 z-20 bg-muted/95 backdrop-blur-sm"
                    >
                        <tr class="font-semibold text-foreground">
                            <th
                                class="sticky left-0 z-30 w-24 min-w-24 border-r border-b border-border bg-muted px-2 py-2 text-center font-semibold whitespace-nowrap text-foreground"
                            >
                                Data
                            </th>
                            <th
                                class="sticky left-24 z-30 w-20 min-w-20 border-r border-b border-border bg-muted px-2 py-2 text-center font-semibold whitespace-nowrap text-foreground"
                            >
                                Hora
                            </th>

                            <th
                                v-for="parameter in sysSection.visibleParameters"
                                :key="parameter.id"
                                class="max-w-36 min-w-28 border-r border-b border-border px-2 py-2 text-center align-top last:border-r-0"
                            >
                                <div class="flex flex-col items-center gap-1">
                                    <span
                                        class="line-clamp-2 w-full text-[11px] leading-tight font-bold text-foreground"
                                        :title="parameter.name"
                                    >
                                        {{ parameter.name }}
                                    </span>

                                    <span
                                        v-if="
                                            getCategoryMeta(parameter.category)
                                        "
                                        class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold"
                                        :class="
                                            getCategoryMeta(parameter.category)
                                                ?.badgeClass
                                        "
                                    >
                                        {{
                                            getCategoryMeta(parameter.category)
                                                ?.code
                                        }}
                                    </span>

                                    <span
                                        v-if="parameter.tag || parameter.code"
                                        class="w-full truncate text-[9px] font-normal text-muted-foreground"
                                        :title="`TAG: ${parameter.tag || parameter.code}`"
                                    >
                                        TAG:
                                        {{ parameter.tag || parameter.code }}
                                    </span>

                                    <span
                                        class="w-full truncate text-[9.5px] font-normal text-muted-foreground"
                                        :title="
                                            parameter.unit
                                                ? `Unidade: ${parameter.unit}`
                                                : undefined
                                        "
                                    >
                                        {{ parameter.unit || '–' }}
                                    </span>

                                    <span
                                        v-if="hasLimits(parameter)"
                                        class="inline-block rounded-full bg-[var(--report-limit-bg)] px-2 py-0.5 text-[9.5px] font-semibold text-[var(--report-limit-ink)]"
                                        :title="`Limites: ${formatLimitsLabel(parameter)}`"
                                    >
                                        {{ formatLimitsLabel(parameter) }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-[9.5px] text-muted-foreground"
                                    >
                                        —
                                    </span>

                                    <div
                                        class="mt-0.5 flex items-center justify-center gap-1"
                                    >
                                        <button
                                            v-if="
                                                parameter.tag || parameter.code
                                            "
                                            type="button"
                                            class="inline-flex size-4 items-center justify-center rounded text-muted-foreground transition hover:bg-background hover:text-foreground"
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
                                            class="inline-flex size-4 items-center justify-center rounded border border-border bg-background text-foreground transition hover:bg-muted"
                                            title="Gráfico em linha"
                                            @click="
                                                emit(
                                                    'chart',
                                                    parameter.id,
                                                    'line',
                                                )
                                            "
                                        >
                                            <LineChart class="size-2.5" />
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex size-4 items-center justify-center rounded border border-border bg-background text-foreground transition hover:bg-muted"
                                            title="Gráfico em barras"
                                            @click="
                                                emit(
                                                    'chart',
                                                    parameter.id,
                                                    'bar',
                                                )
                                            "
                                        >
                                            <BarChart3 class="size-2.5" />
                                        </button>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(row, rowIndex) in sysSection.rows"
                            :key="`${sysSection.id}-${row.timestamp}`"
                            class="transition hover:bg-[var(--report-hover)]"
                            :class="[
                                isReferenceRow(row)
                                    ? 'bg-[var(--report-ref-row)]'
                                    : 'bg-card',
                            ]"
                        >
                            <td
                                class="sticky left-0 z-10 w-24 min-w-24 border-r border-b border-border px-2 py-1.5 text-center font-medium whitespace-nowrap"
                                :class="[
                                    isDayBreak(sysSection.rows, rowIndex)
                                        ? 'border-t-2 border-t-[#99a9b6]'
                                        : '',
                                    isReferenceRow(row)
                                        ? 'bg-[var(--report-ref-pin)] font-bold text-[var(--report-ref-ink)]'
                                        : 'bg-card text-foreground',
                                ]"
                            >
                                {{ getRowDateBr(row) }}
                            </td>

                            <td
                                class="sticky left-24 z-10 w-20 min-w-20 border-r border-b border-border px-2 py-1.5 text-center whitespace-nowrap"
                                :class="[
                                    isDayBreak(sysSection.rows, rowIndex)
                                        ? 'border-t-2 border-t-[#99a9b6]'
                                        : '',
                                    isReferenceRow(row)
                                        ? 'bg-[var(--report-ref-pin)] font-semibold text-[var(--report-ref-ink)]'
                                        : 'bg-card text-foreground',
                                ]"
                            >
                                <span
                                    class="inline-flex items-center justify-center gap-1"
                                >
                                    <span>{{ getRowTimeLabel(row) }}</span>
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
                            </td>

                            <td
                                v-for="parameter in sysSection.visibleParameters"
                                :key="`${row.timestamp}-${parameter.id}`"
                                class="max-w-36 min-w-28 truncate border-r border-b border-border px-2 py-1.5 text-center last:border-r-0"
                                :class="[
                                    isDayBreak(sysSection.rows, rowIndex)
                                        ? 'border-t-2 border-t-[#99a9b6]'
                                        : '',
                                    isValueOutsideLimits(
                                        row.values[parameter.id],
                                        parameter,
                                    )
                                        ? 'font-bold text-[#ca3037] dark:text-[#ff868b]'
                                        : 'text-foreground',
                                ]"
                            >
                                {{
                                    formatNumericValue(
                                        row.values[parameter.id],
                                        parameter.decimals,
                                    )
                                }}
                            </td>
                        </tr>

                        <tr v-if="sysSection.rows.length === 0">
                            <td
                                :colspan="
                                    sysSection.visibleParameters.length + 2
                                "
                                class="px-4 py-8 text-center text-xs text-muted-foreground"
                            >
                                Nenhum registro encontrado no período
                                selecionado.
                            </td>
                        </tr>
                    </tbody>

                    <tfoot
                        v-if="sysSection.rows.length > 0"
                        class="bg-muted/70 font-semibold"
                    >
                        <tr>
                            <td
                                class="sticky left-0 z-10 border-t-2 border-r border-border bg-muted px-2 py-1.5 text-center text-foreground"
                            >
                                Média
                            </td>
                            <td
                                class="sticky left-24 z-10 border-t-2 border-r border-border bg-muted px-2 py-1.5 text-center text-muted-foreground"
                            >
                                Período
                            </td>
                            <td
                                v-for="parameter in sysSection.visibleParameters"
                                :key="`mean-${parameter.id}`"
                                class="border-t-2 border-r border-border px-2 py-1.5 text-center text-foreground last:border-r-0"
                            >
                                {{
                                    getColumnAverage(parameter, sysSection.rows)
                                }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
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
