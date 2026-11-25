<script setup lang="ts">
import {
    Bot,
    Check,
    ChevronDown,
    Layers,
    Loader2,
    MessageSquareText,
    Table,
    X,
} from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ReportDataTable from '@/features/reports/components/ReportDataTable.vue';
import ReportEditorLauncher from '@/features/reports/components/ReportEditorLauncher.vue';
import {
    hasSavedReportComment,
    resolveReportSystemStatus,
} from '@/features/reports/systemStatus';
import type {
    ReportPhrase,
    ReportSystem,
    ReportTableData,
} from '@/features/reports/types';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';
import type { Team } from '@/shared/types';

interface ReportItem {
    id: number;
    parent_report_item_id?: number | null;
    parent_monitored_system_id?: number | null;
    show_data_results: boolean;
    hide_data: boolean;
    is_stopped: boolean;
    comment: string | null;
    document?: Record<string, unknown> | null;
    charts?: Record<string, ChartSeries[]>;
    updated_at: string;
    comments: Array<{
        id: number;
        author: string;
        body: string;
        is_consucal: boolean;
        created_at: string;
    }>;
    suggestions: Array<{
        id: number;
        mode: 'normal' | 'proofread';
        original_text: string;
        replacement_text: string;
        reason: string | null;
    }>;
}

interface SystemSection {
    id: number;
    name: string;
    sort_order: number;
    default_hide_data?: boolean;
    item: ReportItem | null;
}

const props = withDefaults(
    defineProps<{
        system: SystemSection;
        groupedChildren: SystemSection[];
        combinedTitle: string;
        canGroup: boolean;
        availableGroupTargets?: Array<{ id: number; label: string }>;
        isGrouping?: boolean;
        editorReloadTrigger?: number;
        isHighlighted: boolean;
        systemDates: { start_date: string; end_date: string };
        isLoadingData: boolean;
        isTogglingHideData?: boolean;
        systemData?: ReportTableData;
        dataCountsBySystem?: Record<number, number>;
        currentTeam: Team;
        systems: ReportSystem[];
        reportDateReference: string;
        resolvingSuggestions: Record<number, boolean>;
        isLateralMode?: boolean;
        displaySignal?: {
            seq: number;
            mode: 'editor' | 'both' | 'close';
        } | null;
        focusSignal?: { seq: number; openTable?: boolean } | null;
    }>(),
    {
        availableGroupTargets: () => [],
        isGrouping: false,
        editorReloadTrigger: 0,
        isTogglingHideData: false,
        systemData: undefined,
        dataCountsBySystem: undefined,
        isLateralMode: false,
        displaySignal: null,
        focusSignal: null,
    },
);

const emit = defineEmits<{
    groupTarget: [sourceSystem: SystemSection, targetSystemId: number];
    ungroupMember: [member: SystemSection];
    toggleHideData: [system: SystemSection];
    toggleSystem: [system: SystemSection, checked: boolean];
    loadData: [
        system: SystemSection,
        force?: boolean,
        dates?: { start_date: string; end_date: string },
    ];
    chart: [system: SystemSection, parameterId: number, type: 'line' | 'bar'];
    resolveSuggestion: [suggestionId: number, decision: 'accept' | 'reject'];
    editorSaved: [item?: ReportPhrase | null];
    editingChange: [systemId: number, editing: boolean];
    dirtyChange: [systemId: number, dirty: boolean];
}>();

const isSaved = computed(() => hasSavedReportComment(props.system.item));

const isBlockOpen = ref(props.isLateralMode || isSaved.value);
const isEditing = ref(props.isLateralMode && !isSaved.value);
const isTableOpen = ref(false);
const isGroupPickerOpen = ref(false);
const selectedTargetSystemId = ref<number | ''>('');
const groupPickerRef = ref<HTMLElement | null>(null);
const groupButtonRef = ref<HTMLElement | null>(null);
const groupSelectRef = ref<HTMLSelectElement | null>(null);

watch(
    () => props.isLateralMode,
    (lateral) => {
        if (lateral) {
            isBlockOpen.value = true;

            if (!isSaved.value && !props.system.item?.show_data_results) {
                emit('toggleSystem', props.system, true);
                isEditing.value = true;
            } else if (!isSaved.value) {
                isEditing.value = true;
            }
        }
    },
);

watch(
    () => props.displaySignal?.seq,
    () => {
        if (!props.displaySignal || props.isLateralMode) {
            return;
        }

        const { mode } = props.displaySignal;

        if (mode === 'close') {
            if (isEditing.value) {
                return;
            }

            isBlockOpen.value = false;
            isTableOpen.value = false;

            return;
        }

        if (!props.system.item?.show_data_results) {
            emit('toggleSystem', props.system, true);
        }

        isBlockOpen.value = true;
        isEditing.value = !isSaved.value;

        if (mode === 'both') {
            isTableOpen.value = true;

            if (!props.systemData && !props.isLoadingData) {
                emit('loadData', props.system);
            }
        } else {
            isTableOpen.value = false;
        }
    },
);

watch(
    () => props.focusSignal?.seq,
    () => {
        if (!props.focusSignal) {
            return;
        }

        if (!props.system.item?.show_data_results) {
            emit('toggleSystem', props.system, true);
        }

        isBlockOpen.value = true;

        if (!isSaved.value) {
            isEditing.value = true;
        }

        if (props.focusSignal.openTable) {
            isTableOpen.value = true;

            if (!props.systemData && !props.isLoadingData) {
                emit('loadData', props.system);
            }
        }
    },
);

watch(isEditing, (editing) => {
    emit('editingChange', props.system.id, editing);
});

function handleEscapeKey(event: KeyboardEvent): void {
    if (event.key === 'Escape' && isGroupPickerOpen.value) {
        isGroupPickerOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleEscapeKey);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscapeKey);

    if (isEditing.value) {
        emit('editingChange', props.system.id, false);
    }
});

onClickOutside(
    groupPickerRef,
    () => {
        isGroupPickerOpen.value = false;
    },
    { ignore: [groupButtonRef] },
);

const isDataHidden = computed(
    () =>
        props.system.item?.hide_data ?? props.system.default_hide_data ?? false,
);
const isTableVisibleInFinalReport = computed(() => !isDataHidden.value);

const systemStatus = computed(() =>
    resolveReportSystemStatus(props.system.item, isEditing.value),
);

const headerStatusBorderClass = computed(() => {
    if (systemStatus.value === 'enabled') {
        return 'border-l-emerald-500';
    }

    if (systemStatus.value === 'editing') {
        return 'border-l-amber-500';
    }

    return 'border-l-red-500';
});

const memberRows = computed(() => [props.system, ...props.groupedChildren]);
const isGrouped = computed(() => props.groupedChildren.length > 0);

const observationsCount = computed(() => {
    if (!props.systemData?.rows) {
        return 0;
    }

    return props.systemData.rows.filter((row) => row.has_comments).length;
});

function getMemberDataCount(memberId: number): number | null {
    if (!props.dataCountsBySystem) {
        return null;
    }

    return props.dataCountsBySystem[memberId] ?? 0;
}

function formatDataCountLabel(count: number): string {
    if (count === 0) {
        return 'Sem dados';
    }

    return count === 1 ? '1 dado' : `${count} dados`;
}

function handleHeaderClick(): void {
    if (props.isLateralMode) {
        return;
    }

    if (isBlockOpen.value) {
        if (isEditing.value) {
            const proceed = window.confirm(
                'Há alterações em edição neste sistema. Deseja fechar o cartão e cancelar a edição?',
            );

            if (!proceed) {
                return;
            }
        }

        isBlockOpen.value = false;
        isEditing.value = false;
        isGroupPickerOpen.value = false;
    } else {
        if (!props.system.item?.show_data_results) {
            emit('toggleSystem', props.system, true);
        }

        isEditing.value = !isSaved.value;
        isBlockOpen.value = true;
    }
}

function handleHeaderKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ' ') {
        if (event.target === event.currentTarget) {
            event.preventDefault();
            handleHeaderClick();
        }
    }
}

function handleEditorInput(): void {
    if (!isEditing.value) {
        isEditing.value = true;
    }
}

function handleSaved(savedItem: ReportPhrase | null): void {
    isEditing.value = false;
    emit('editorSaved', savedItem);
}

function handleCancelEditing(): void {
    isEditing.value = false;
    isGroupPickerOpen.value = false;

    if (!props.isLateralMode) {
        isBlockOpen.value = false;
    }
}

function toggleTable(): void {
    isTableOpen.value = !isTableOpen.value;

    if (isTableOpen.value && !props.systemData && !props.isLoadingData) {
        emit('loadData', props.system);
    }
}

function toggleGroupPicker(): void {
    isGroupPickerOpen.value = !isGroupPickerOpen.value;

    if (isGroupPickerOpen.value) {
        selectedTargetSystemId.value = '';
        void nextTick(() => {
            groupSelectRef.value?.focus();
        });
    }
}

function confirmInlineGroup(): void {
    if (!selectedTargetSystemId.value) {
        return;
    }

    emit('groupTarget', props.system, Number(selectedTargetSystemId.value));
    selectedTargetSystemId.value = '';
    isGroupPickerOpen.value = false;
}

function handleUngroupMember(member: SystemSection): void {
    isGroupPickerOpen.value = false;
    emit('ungroupMember', member);
}
</script>

<template>
    <section
        :data-report-system-id="system.id"
        class="min-w-0 scroll-mt-28 overflow-visible rounded-xl border bg-card transition duration-200"
        :class="[
            isHighlighted
                ? 'border-primary shadow-lg ring-2 ring-primary/35'
                : 'border-border shadow-xs',
        ]"
    >
        <!-- Cabeçalho do Cartão -->
        <header
            role="button"
            tabindex="0"
            :aria-expanded="isBlockOpen"
            class="flex flex-wrap items-center justify-between gap-3 border-l-[6px] px-3.5 py-2.5 transition select-none"
            :class="[
                headerStatusBorderClass,
                !isLateralMode ? 'cursor-pointer hover:bg-muted/40' : '',
                isBlockOpen
                    ? 'rounded-tl-[11px] rounded-tr-[11px] border-b border-border/70'
                    : 'rounded-[11px]',
            ]"
            @click="handleHeaderClick"
            @keydown="handleHeaderKeydown"
        >
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <!-- Nomes dos Sistemas (Agrupados exibem todos os integrantes com contagem à direita) -->
                <div class="min-w-0 flex-1 space-y-1">
                    <div
                        v-if="isGrouped"
                        class="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-[9.5px] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <span
                            >↗ Sistemas agrupados ·
                            {{ memberRows.length }} sistemas</span
                        >
                    </div>

                    <div
                        v-for="member in memberRows"
                        :key="member.id"
                        class="flex items-center justify-between gap-3"
                    >
                        <h2
                            class="truncate text-[13px] font-semibold text-foreground"
                        >
                            {{ member.name }}
                        </h2>
                        <span
                            v-if="getMemberDataCount(member.id) !== null"
                            data-test="system-data-count"
                            class="inline-flex shrink-0 items-center rounded border border-border bg-muted/60 px-2 py-0.5 text-[11px] font-semibold text-muted-foreground tabular-nums"
                        >
                            {{
                                formatDataCountLabel(
                                    getMemberDataCount(member.id)!,
                                )
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Direita do Cabeçalho: Em edição + Seta -->
            <div class="flex shrink-0 items-center gap-2">
                <span
                    v-if="isEditing"
                    class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                >
                    <span class="size-1.5 rounded-full bg-amber-500" />
                    Em edição
                </span>

                <ChevronDown
                    v-if="!isLateralMode"
                    class="size-4 text-muted-foreground transition-transform duration-200"
                    :class="isBlockOpen ? 'rotate-180' : ''"
                />
            </div>
        </header>

        <!-- Conteúdo do Bloco (1º Tabela Histórica em Amarelo-Claro, 2º Considerações Consucal) -->
        <div
            v-if="isBlockOpen"
            class="min-w-0 space-y-2.5 bg-muted p-3 lg:p-3.5"
        >
            <!-- 1. Acordeão Tabela de Dados (Histórico) no Topo -->
            <div
                class="overflow-hidden rounded-xl border border-[var(--report-history-border)] bg-[var(--report-history-bg)]"
            >
                <button
                    type="button"
                    :aria-expanded="isTableOpen"
                    class="flex w-full items-center justify-between gap-2 bg-[var(--report-history-head)] px-3.5 py-1.5 text-left text-[11.5px] font-semibold text-[var(--report-history-ink)] transition hover:bg-[var(--report-history-head-hover)]"
                    @click="toggleTable"
                >
                    <span class="inline-flex flex-wrap items-center gap-2">
                        <Table class="size-3.5 shrink-0" />
                        <span>Tabela de Dados (Histórico)</span>
                        <span
                            v-if="observationsCount > 0"
                            class="inline-flex items-center gap-1 text-[10.5px] font-medium text-[var(--report-history-count)]"
                        >
                            <MessageSquareText class="size-3" />
                            {{ observationsCount }}
                            {{
                                observationsCount === 1
                                    ? 'observação'
                                    : 'observações'
                            }}
                        </span>
                    </span>

                    <ChevronDown
                        class="size-4 shrink-0 transition-transform duration-200"
                        :class="isTableOpen ? 'rotate-180' : ''"
                    />
                </button>

                <div
                    v-if="isTableOpen"
                    data-test="system-data-table-container"
                    class="border-t border-[var(--report-history-border)] bg-card p-3"
                >
                    <div
                        v-if="isLoadingData && !systemData"
                        class="grid h-32 place-items-center"
                    >
                        <Loader2 class="size-6 animate-spin text-primary" />
                    </div>
                    <ReportDataTable
                        v-else-if="systemData"
                        :parameters="systemData.parameters"
                        :rows="systemData.rows"
                        :averages="systemData.averages"
                        :start-date="systemDates.start_date"
                        :end-date="systemDates.end_date"
                        :report-date-reference="reportDateReference"
                        :is-loading-data="isLoadingData"
                        :primary-system="{ id: system.id, name: system.name }"
                        :grouped-children="groupedChildren"
                        @chart="
                            (parameterId, type) =>
                                emit('chart', system, parameterId, type)
                        "
                    />
                </div>
            </div>

            <!-- 2. Considerações Consucal (Controles no canto direito do cabeçalho) -->
            <div
                class="min-w-0 overflow-hidden rounded-xl border border-border bg-card shadow-2xs"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted px-3.5 py-2"
                >
                    <div class="min-w-0">
                        <h3 class="text-[11.5px] font-semibold text-foreground">
                            Considerações Consucal
                            <span
                                v-if="isGrouped"
                                class="font-medium text-muted-foreground"
                            >
                                · compartilhadas ({{ combinedTitle }})
                            </span>
                        </h3>
                        <p
                            v-if="isTableOpen"
                            class="mt-0.5 text-[10.5px] font-medium text-muted-foreground"
                        >
                            Sistema: {{ combinedTitle }}
                        </p>
                    </div>

                    <!-- Ações de Apresentação e Agrupamento -->
                    <div
                        v-if="systemStatus !== 'disabled'"
                        data-test="system-block-actions"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <div
                            class="flex items-center gap-2 rounded-lg border border-border bg-card px-2.5 py-1 shadow-2xs transition-opacity"
                            :class="isTogglingHideData ? 'opacity-80' : ''"
                            :title="
                                isTableVisibleInFinalReport
                                    ? 'Ativo: tabela visível no relatório final do usuário. Desative para ocultar apenas no relatório do usuário.'
                                    : 'Desativado: tabela oculta no relatório final do usuário (continua visível nesta tela).'
                            "
                            @click.stop
                        >
                            <button
                                :id="`show-table-slider-${system.id}`"
                                type="button"
                                role="switch"
                                :aria-checked="isTableVisibleInFinalReport"
                                :aria-busy="isTogglingHideData || undefined"
                                :disabled="isTogglingHideData"
                                data-test="toggle-hide-data-slider"
                                class="relative inline-flex h-4 w-7 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-ring focus:ring-offset-1 focus:outline-hidden"
                                :class="[
                                    isTableVisibleInFinalReport
                                        ? 'bg-emerald-600'
                                        : 'bg-muted-foreground/30',
                                    isTogglingHideData
                                        ? 'cursor-wait opacity-70'
                                        : 'cursor-pointer',
                                ]"
                                @click="emit('toggleHideData', system)"
                            >
                                <span
                                    aria-hidden="true"
                                    class="pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                    :class="
                                        isTableVisibleInFinalReport
                                            ? 'translate-x-3'
                                            : 'translate-x-0'
                                    "
                                />
                            </button>
                            <label
                                :for="`show-table-slider-${system.id}`"
                                class="inline-flex items-center gap-1.5 text-[11px] font-medium text-foreground select-none"
                                :class="
                                    isTogglingHideData
                                        ? 'cursor-wait'
                                        : 'cursor-pointer'
                                "
                            >
                                <span>Tabela inicialmente visível</span>
                                <Loader2
                                    v-if="isTogglingHideData"
                                    data-test="hide-data-loading"
                                    class="size-3 animate-spin text-muted-foreground"
                                />
                            </label>
                        </div>

                        <button
                            v-if="canGroup || isGrouped"
                            ref="groupButtonRef"
                            type="button"
                            data-test="toggle-inline-group"
                            :aria-expanded="isGroupPickerOpen"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-medium shadow-2xs transition"
                            :class="
                                isGroupPickerOpen
                                    ? 'border-primary/40 bg-primary/10 text-primary'
                                    : 'border-border bg-card text-foreground hover:bg-accent'
                            "
                            :title="
                                isGrouped
                                    ? 'Alterar composição do agrupamento'
                                    : 'Agrupar com outro sistema'
                            "
                            @click.stop="toggleGroupPicker"
                        >
                            <Layers class="size-3.5 text-muted-foreground" />
                            <span>{{
                                isGrouped ? 'Agrupamento' : 'Agrupar'
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Painel Inline de Agrupamento (abre na mesma tela, trazendo outro sistema para cá) -->
                <div
                    v-if="isGroupPickerOpen"
                    ref="groupPickerRef"
                    data-test="inline-group-picker"
                    class="flex flex-wrap items-start gap-6 border-b border-border bg-muted/45 px-3.5 py-3"
                    @click.stop
                >
                    <div class="flex min-w-[240px] flex-col gap-2">
                        <span
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            {{
                                isGrouped
                                    ? 'Adicionar ao agrupamento'
                                    : 'Agrupar sistemas'
                            }}
                        </span>
                        <label
                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span>Sistema:</span>
                            <select
                                ref="groupSelectRef"
                                v-model="selectedTargetSystemId"
                                data-test="inline-group-target-select"
                                class="h-8 max-w-[300px] min-w-[210px] rounded-md border border-border bg-card px-2.5 text-xs font-medium text-foreground shadow-2xs focus:ring-2 focus:ring-primary/40 focus:outline-hidden"
                            >
                                <option value="" disabled>
                                    Selecione um sistema...
                                </option>
                                <option
                                    v-for="target in availableGroupTargets"
                                    :key="target.id"
                                    :value="target.id"
                                >
                                    {{ target.label }}
                                </option>
                            </select>
                        </label>
                        <div class="flex flex-col items-start gap-1.5">
                            <button
                                type="button"
                                data-test="confirm-inline-group"
                                :disabled="
                                    !selectedTargetSystemId || isGrouping
                                "
                                class="inline-flex h-8 items-center gap-1.5 rounded-md bg-[#24658e] px-3 text-xs font-semibold text-white shadow-2xs transition hover:bg-[#1d5274] disabled:cursor-not-allowed disabled:opacity-50"
                                @click="confirmInlineGroup"
                            >
                                <Loader2
                                    v-if="isGrouping"
                                    class="size-3.5 animate-spin"
                                />
                                <Check v-else class="size-3.5" />
                                <span>Confirmar agrupamento</span>
                            </button>
                            <small class="text-[10.5px] text-muted-foreground">
                                As considerações já salvas são preservadas no
                                agrupamento.
                            </small>
                        </div>
                    </div>

                    <div
                        v-if="isGrouped"
                        class="flex min-w-[230px] flex-col gap-2"
                    >
                        <span
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Retirar do agrupamento
                        </span>
                        <div class="flex flex-col items-stretch gap-1.5">
                            <button
                                v-for="member in memberRows"
                                :key="member.id"
                                type="button"
                                data-test="ungroup-member-button"
                                :disabled="isGrouping"
                                :title="`Desagrupar ${member.name}`"
                                class="flex max-w-[340px] min-w-[230px] items-center justify-between gap-2 rounded-md border border-border bg-card px-2.5 py-1.5 text-left text-xs font-medium text-foreground shadow-2xs transition hover:border-red-300 hover:bg-muted/50 disabled:opacity-50"
                                @click="handleUngroupMember(member)"
                            >
                                <span class="truncate">{{ member.name }}</span>
                                <span
                                    aria-hidden="true"
                                    class="grid size-5 shrink-0 place-items-center rounded bg-[#ffe5e6] text-[#b6222b] dark:bg-[#663139] dark:text-[#ffb0b5]"
                                >
                                    <X class="size-3.5" />
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Corpo das Considerações (RichText sempre aberto quando o sistema é expandido) -->
                <div
                    class="min-w-0 p-3.5 lg:p-4"
                    @input="handleEditorInput"
                    @paste="handleEditorInput"
                >
                    <ReportEditorLauncher
                        :current-team="currentTeam"
                        :systems="systems"
                        :selected-system-ids="[system.id]"
                        :default-date="reportDateReference"
                        :auto-open="true"
                        :inline="true"
                        :hide-data="isDataHidden"
                        :system-name="system.name"
                        :reload-trigger="editorReloadTrigger"
                        @saved="handleSaved"
                        @cancel="handleCancelEditing"
                        @dirty-change="
                            (dirty) => emit('dirtyChange', system.id, dirty)
                        "
                    />
                </div>
            </div>

            <!-- 3. Bloco de Sugestões de IA (se houver) -->
            <div
                v-if="system.item?.suggestions.length"
                class="grid gap-2 rounded-xl border border-violet-200 bg-violet-50/60 p-4 dark:border-violet-900/40 dark:bg-violet-950/15"
            >
                <div
                    v-for="suggestion in system.item.suggestions"
                    :key="suggestion.id"
                    class="rounded-xl border border-violet-200 bg-card p-3 text-sm dark:border-violet-900"
                    :title="suggestion.reason ?? ''"
                >
                    <div class="flex items-start gap-2">
                        <Bot class="mt-0.5 size-4 shrink-0 text-violet-600" />
                        <div class="min-w-0 flex-1">
                            <p>
                                <del class="text-red-600">{{
                                    suggestion.original_text
                                }}</del>
                            </p>
                            <p
                                class="mt-1 text-emerald-700 dark:text-emerald-300"
                            >
                                {{ suggestion.replacement_text }}
                            </p>
                            <p
                                v-if="suggestion.reason"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ suggestion.reason }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <button
                            type="button"
                            class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                            :disabled="resolvingSuggestions[suggestion.id]"
                            @click="
                                emit(
                                    'resolveSuggestion',
                                    suggestion.id,
                                    'accept',
                                )
                            "
                        >
                            <Loader2
                                v-if="resolvingSuggestions[suggestion.id]"
                                class="mr-1 inline size-3 animate-spin"
                            />
                            <Check v-else class="mr-1 inline size-3" />Aprovar
                        </button>
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-xs transition hover:bg-muted disabled:opacity-50"
                            :disabled="resolvingSuggestions[suggestion.id]"
                            @click="
                                emit(
                                    'resolveSuggestion',
                                    suggestion.id,
                                    'reject',
                                )
                            "
                        >
                            <Loader2
                                v-if="resolvingSuggestions[suggestion.id]"
                                class="mr-1 inline size-3 animate-spin"
                            />
                            <X v-else class="mr-1 inline size-3" />Rejeitar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
