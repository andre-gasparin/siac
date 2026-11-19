<script setup lang="ts">
import {
    Bot,
    Check,
    ChevronDown,
    Layers,
    Loader2,
    Pencil,
    Table,
    X,
} from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, onUnmounted, ref, watch } from 'vue';
import ReportCommentContent from '@/features/reports/components/ReportCommentContent.vue';
import ReportDataTable from '@/features/reports/components/ReportDataTable.vue';
import ReportEditorLauncher from '@/features/reports/components/ReportEditorLauncher.vue';
import {
    hasSavedReportComment,
    resolveReportSystemStatus,
} from '@/features/reports/systemStatus';
import type { ReportPhrase, ReportSystem } from '@/features/reports/types';
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

interface ReportData {
    parameters: Array<{
        id: number;
        name: string;
        code?: string | null;
        tag?: string | null;
        unit?: string | null;
        decimals: number;
        alert_1_min: number | null;
        alert_1_max: number | null;
    }>;
    rows: Array<{
        timestamp: string;
        time: string;
        values: Record<number, number>;
    }>;
}

const props = defineProps<{
    system: SystemSection;
    groupedChildren: SystemSection[];
    combinedTitle: string;
    canGroup: boolean;
    isHighlighted: boolean;
    systemDates: { start_date: string; end_date: string };
    isLoadingData: boolean;
    isTogglingHideData?: boolean;
    systemData?: ReportData;
    currentTeam: Team;
    systems: ReportSystem[];
    reportDateReference: string;
    resolvingSuggestions: Record<number, boolean>;
}>();

const emit = defineEmits<{
    ungroupChild: [child: SystemSection];
    openGroupModal: [system: SystemSection];
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
}>();

const isSaved = computed(() => hasSavedReportComment(props.system.item));

const isBlockOpen = ref(isSaved.value);
const isEditing = ref(false);
const isTableOpen = ref(false);
const confirmDisableOpen = ref(false);
const bolinhaContainerRef = ref<HTMLElement | null>(null);

watch(isEditing, (editing) => {
    emit('editingChange', props.system.id, editing);
});

onUnmounted(() => {
    if (isEditing.value) {
        emit('editingChange', props.system.id, false);
    }
});

onClickOutside(bolinhaContainerRef, () => {
    confirmDisableOpen.value = false;
});

const isDataHidden = computed(
    () =>
        props.system.item?.hide_data ?? props.system.default_hide_data ?? false,
);
const isTableVisibleInFinalReport = computed(() => !isDataHidden.value);

const systemStatus = computed(() =>
    resolveReportSystemStatus(props.system.item, isEditing.value),
);

const statusColor = computed(() => {
    if (systemStatus.value === 'enabled') {
        return 'bg-emerald-500';
    }

    if (systemStatus.value === 'editing') {
        return 'bg-amber-500';
    }

    return 'bg-red-500';
});

const blockOutlineClass = computed(() => {
    if (systemStatus.value === 'enabled') {
        return 'border-emerald-500/60 shadow-sm ring-1 ring-emerald-500/25';
    }

    if (systemStatus.value === 'editing') {
        return 'border-amber-500/60 shadow-sm ring-1 ring-amber-500/25';
    }

    return props.system.item?.show_data_results
        ? 'border-red-500/60 shadow-sm ring-1 ring-red-500/25'
        : 'border-red-500/60 opacity-90 shadow-sm ring-1 ring-red-500/25 hover:opacity-100';
});

const bolinhaTooltip = computed(() => {
    if (!props.system.item?.show_data_results) {
        return 'Sistema desativado. Clique para ativar e abrir';
    }

    if (isEditing.value) {
        return 'Sistema em edição. Clique para desativar e fechar';
    }

    if (isSaved.value) {
        return 'Sistema salvo. Clique para desativar e fechar';
    }

    return 'Sistema desativado. Clique para ativar e abrir';
});

function handleHeaderClick(): void {
    confirmDisableOpen.value = false;

    if (isBlockOpen.value) {
        // Fecha o bloco no cabeçalho
        isBlockOpen.value = false;
        isEditing.value = false;
    } else {
        // Se estiver desativado, ativa o sistema e abre
        if (!props.system.item?.show_data_results) {
            emit('toggleSystem', props.system, true);
        }

        // Se já tem conteúdo salvo, abre em modo de visualização (verde); se não tem, abre direto no editor
        isEditing.value = !isSaved.value;
        isBlockOpen.value = true;
    }
}

function handleBolinhaClick(): void {
    // Se clicar na bolinha amarela ou verde, solicita confirmação antes de desabilitar
    const isDisabling =
        isBlockOpen.value ||
        Boolean(props.system.item?.show_data_results && isSaved.value);

    if (isDisabling) {
        confirmDisableOpen.value = !confirmDisableOpen.value;

        return;
    }

    // Se estiver vermelha, ativa e abre normalmente
    confirmDisableOpen.value = false;
    emit('toggleSystem', props.system, true);
    isEditing.value = !isSaved.value;
    isBlockOpen.value = true;
}

function confirmDisable(): void {
    confirmDisableOpen.value = false;
    isBlockOpen.value = false;
    isEditing.value = false;
    emit('toggleSystem', props.system, false);
}

function cancelDisable(): void {
    confirmDisableOpen.value = false;
}

function handleEditClick(): void {
    confirmDisableOpen.value = false;

    if (!props.system.item?.show_data_results) {
        emit('toggleSystem', props.system, true);
    }

    isBlockOpen.value = true;
    isEditing.value = true;
}

function handleSaved(savedItem: ReportPhrase | null): void {
    // Ao salvar, apenas sai o editor e fica a visualização; não fecha o bloco todo
    isEditing.value = false;
    emit('editorSaved', savedItem);
}

function handleCancelEditing(): void {
    isEditing.value = false;

    if (!isSaved.value) {
        isBlockOpen.value = false;
    }
}

function toggleTable(): void {
    isTableOpen.value = !isTableOpen.value;

    if (isTableOpen.value && !props.systemData && !props.isLoadingData) {
        emit('loadData', props.system);
    }
}

function handleUngroupChild(child: { id: number; name: string }): void {
    const fullChild = props.groupedChildren.find((c) => c.id === child.id);

    if (fullChild) {
        emit('ungroupChild', fullChild);
    }
}
</script>

<template>
    <section
        :data-report-system-id="system.id"
        class="min-w-0 overflow-visible rounded-xl border bg-card transition duration-300"
        :class="[
            !isHighlighted && blockOutlineClass,
            isHighlighted &&
                'border-violet-400 shadow-xl ring-4 shadow-violet-950/15 ring-violet-400/50',
        ]"
    >
        <header
            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 px-4 py-3 transition select-none hover:bg-muted/40"
            :class="[
                isBlockOpen ? 'rounded-t-xl border-b' : 'rounded-xl',
                system.item?.show_data_results
                    ? 'border-border/60 bg-muted/20 text-foreground'
                    : 'border-border/50 bg-muted/20 text-muted-foreground',
            ]"
            @click="handleHeaderClick"
        >
            <div class="flex flex-wrap items-center gap-3">
                <div
                    ref="bolinhaContainerRef"
                    class="relative flex items-center"
                >
                    <button
                        type="button"
                        data-test="system-status-indicator"
                        class="size-3.5 shrink-0 rounded-full ring-2 ring-background transition-transform hover:scale-125 focus:outline-hidden"
                        :class="statusColor"
                        :title="bolinhaTooltip"
                        :aria-label="bolinhaTooltip"
                        @click.stop="handleBolinhaClick"
                    />

                    <div
                        v-if="confirmDisableOpen"
                        data-test="disable-system-confirmation"
                        class="absolute top-full left-0 z-50 mt-2 w-64 rounded-xl border border-border bg-popover p-3 text-xs text-popover-foreground shadow-xl ring-1 ring-border/50"
                        @click.stop
                    >
                        <p class="leading-relaxed font-medium text-foreground">
                            Deseja realmente desabilitar este sistema?
                        </p>
                        <div
                            class="mt-2.5 flex items-center justify-end gap-1.5"
                        >
                            <button
                                type="button"
                                data-test="cancel-disable-system"
                                class="rounded-md border border-border bg-background px-2.5 py-1 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                @click="cancelDisable"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                data-test="confirm-disable-system"
                                class="rounded-md bg-red-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-2xs transition hover:bg-red-700"
                                @click="confirmDisable"
                            >
                                Desabilitar
                            </button>
                        </div>
                    </div>
                </div>

                <h2 class="font-semibold text-foreground">
                    {{ combinedTitle }}
                </h2>
            </div>
            <div
                v-if="systemStatus !== 'disabled'"
                data-test="system-block-actions"
                class="flex items-center gap-2"
            >
                <div
                    class="flex items-center gap-2 rounded-lg border border-border bg-background px-2.5 py-1 shadow-2xs transition-opacity"
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
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground select-none"
                        :class="
                            isTogglingHideData
                                ? 'cursor-wait'
                                : 'cursor-pointer'
                        "
                    >
                        <span>Mostrar tabela</span>
                        <Loader2
                            v-if="isTogglingHideData"
                            data-test="hide-data-loading"
                            class="size-3.5 animate-spin text-muted-foreground"
                        />
                    </label>
                </div>

                <button
                    v-if="canGroup"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1 text-xs font-medium text-foreground shadow-2xs transition hover:bg-muted"
                    title="Agrupar este sistema no primeiro sistema habilitado"
                    @click.stop="emit('openGroupModal', system)"
                >
                    <Layers class="size-3.5 text-muted-foreground" />
                    Agrupar
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1 text-xs font-medium text-foreground shadow-2xs transition hover:bg-muted"
                    title="Editar considerações deste sistema"
                    @click.stop="handleEditClick"
                >
                    <Pencil class="size-3.5 text-muted-foreground" />
                    Editar
                </button>
            </div>
        </header>

        <!-- Conteúdo do Bloco (Exibido quando aberto) -->
        <div v-if="isBlockOpen" class="min-w-0 space-y-4 p-4">
            <!-- 1. Bloco de Considerações: Modo Edição direto (sem borda dupla) -->
            <div v-if="isEditing" class="min-w-0">
                <ReportEditorLauncher
                    :current-team="currentTeam"
                    :systems="systems"
                    :selected-system-ids="[system.id]"
                    :default-date="reportDateReference"
                    :auto-open="true"
                    :inline="true"
                    :hide-data="isDataHidden"
                    :system-name="system.name"
                    @saved="handleSaved"
                    @cancel="handleCancelEditing"
                />
            </div>

            <!-- Modo Visualização das Considerações Salvas (borda única apenas no conteúdo) -->
            <div
                v-else
                class="min-w-0 rounded-xl border border-border/70 bg-card p-4 shadow-2xs"
            >
                <ReportCommentContent
                    v-if="system.item?.comment"
                    :html="system.item.comment"
                    :document="system.item.document"
                    :charts="system.item.charts"
                    :team-slug="currentTeam.slug"
                />
                <p
                    v-else
                    class="rounded-lg border border-dashed border-border/80 p-4 text-xs text-muted-foreground"
                >
                    Nenhuma consideração registrada para este sistema.
                </p>
            </div>

            <!-- 2. Botão para Exibir/Ocultar Tabela de Dados (embaixo do editor) -->
            <div class="flex items-center justify-start">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3.5 py-2 text-xs font-semibold text-foreground shadow-2xs transition hover:bg-muted"
                    @click="toggleTable"
                >
                    <Table class="size-4 text-muted-foreground" />
                    <span>{{
                        isTableOpen
                            ? 'Ocultar tabela de dados'
                            : 'Exibir tabela de dados'
                    }}</span>
                    <ChevronDown
                        class="size-3.5 text-muted-foreground transition-transform duration-200"
                        :class="isTableOpen ? 'rotate-180' : ''"
                    />
                </button>
            </div>

            <!-- 3. Tabela de Dados (fechada por padrão, aberta via botão) -->
            <div
                v-if="isTableOpen"
                data-test="system-data-table-container"
                class="min-w-0 overflow-hidden rounded-xl border border-border/70 bg-card p-4 shadow-2xs"
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
                    :start-date="systemDates.start_date"
                    :end-date="systemDates.end_date"
                    :is-loading-data="isLoadingData"
                    :grouped-children="groupedChildren"
                    @ungroup-child="handleUngroupChild"
                    @chart="
                        (parameterId, type) =>
                            emit('chart', system, parameterId, type)
                    "
                />
            </div>

            <!-- 4. Bloco de Sugestões de IA (se houver) -->
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
