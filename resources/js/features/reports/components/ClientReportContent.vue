<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ClipboardList,
    Link2,
    Loader2,
    Mail,
    MessageSquare,
    MessageSquareText,
    Plus,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import ClientReportDeviationsModal from '@/features/reports/components/ClientReportDeviationsModal.vue';
import type { SummarySystemRow } from '@/features/reports/components/ClientReportSummaryPanel.vue';
import ClientReportToolbar from '@/features/reports/components/ClientReportToolbar.vue';
import type {
    CentralCommentEntry,
    ViewPreferences,
} from '@/features/reports/components/ClientReportToolbar.vue';
import ParameterChartModal from '@/features/reports/components/ParameterChartModal.vue';
import ReportCommentContent from '@/features/reports/components/ReportCommentContent.vue';
import ReportHorizontalDataTable from '@/features/reports/components/ReportHorizontalDataTable.vue';
import {
    computeSystemStats,
    formatDateBr,
    formatDateTimeBr,
} from '@/features/reports/reportMetrics';
import type { DeviationEntry } from '@/features/reports/reportMetrics';
import type {
    ReportTableData,
    ReportTableParameter,
} from '@/features/reports/types';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';
import { Button } from '@/shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';
import type { Team } from '@/shared/types';

interface ReportComment {
    id: number;
    author: string;
    body: string;
    is_consucal: boolean;
    is_own?: boolean;
    created_at: string;
    notified_at?: string | null;
}

interface ReportItem {
    id: number;
    show_data_results: boolean;
    hide_data: boolean;
    is_stopped: boolean;
    comment: string | null;
    document?: Record<string, unknown> | null;
    charts?: Record<string, ChartSeries[]>;
    comments: ReportComment[];
}

interface SystemSection {
    id: number;
    name: string;
    sort_order: number;
    item: ReportItem;
}

interface RecipientInfo {
    id?: number;
    name: string;
    email: string;
    last_sent_at?: string | null;
    last_accessed_at?: string | null;
    send_count?: number;
    is_admin?: boolean;
    revoked_at?: string | null;
    is_queued?: boolean;
}

const props = withDefaults(
    defineProps<{
        report: {
            id: number;
            title: string;
            status: string;
            date_reference: string;
            team_name: string;
            team_logo_url?: string | null;
            finished_at?: string | null;
            emailed_at?: string | null;
            email_count?: number;
            issuer_name?: string | null;
        };
        systems: SystemSection[];
        dataBySystem: Record<number, ReportTableData>;
        recipients?: RecipientInfo[];
        recipient?: RecipientInfo | null;
        activeRecipientsCount?: number;
        isPreview?: boolean;
        currentTeam?: Team;
        commentSubmitUrlGenerator: (systemItemId?: number) => string;
        notifyInteractionsUrl?: string;
        chartUrlGenerator: (
            systemId: number,
            parameterId: number,
            type: 'line' | 'bar',
        ) => string;
    }>(),
    {
        recipients: () => [],
        recipient: null,
        activeRecipientsCount: undefined,
        isPreview: false,
        currentTeam: undefined,
        notifyInteractionsUrl: undefined,
    },
);

const emit = defineEmits<{
    openRecipients: [];
}>();

const createDefaultPreferences = (): ViewPreferences => ({
    tableFont: 13,
    technicalFont: 14,
    categories: {
        ON: true,
        LAB: true,
        OP: true,
        CALC: true,
        PORT: true,
    },
    showTags: true,
    showMean: true,
    showCharts: true,
});

const preferences = ref<ViewPreferences>(createDefaultPreferences());
const compactBlockIds = reactive<Record<number, boolean>>({});
const tableOpenBySystem = reactive<Record<number, boolean>>({});
const groupModeByBlock = reactive<Record<number, 'system' | 'parameter'>>({});
const repliesOpenByBlock = reactive<Record<number, boolean>>({});
const composingByBlock = reactive<Record<number, boolean>>({});
const commentBodies = reactive<Record<number, string>>({});
const isSubmittingComment = reactive<Record<number, boolean>>({});
const showRecipientsList = ref(false);
const highlightedBlockId = ref<number | null>(null);

const chart = ref<{ open: boolean; url: string; title: string }>({
    open: false,
    url: '',
    title: '',
});

const deviationsModal = ref<{
    open: boolean;
    systemName: string;
    deviations: DeviationEntry[];
}>({
    open: false,
    systemName: '',
    deviations: [],
});

const activeCount = computed(
    () =>
        props.activeRecipientsCount ??
        props.recipients.filter((r) => !r.revoked_at).length,
);

const sentRecipientsCount = computed(
    () => props.recipients.filter((r) => Boolean(r.last_sent_at)).length,
);

const accessedRecipientsCount = computed(
    () => props.recipients.filter((r) => Boolean(r.last_accessed_at)).length,
);

const queuedRecipientsCount = computed(
    () =>
        props.recipients.filter((r) => !r.revoked_at && Boolean(r.is_queued))
            .length,
);

function isTableOpen(system: SystemSection): boolean {
    if (system.item.hide_data || !props.dataBySystem[system.id]) {
        return false;
    }

    if (system.id in tableOpenBySystem) {
        return tableOpenBySystem[system.id];
    }

    return true;
}

function toggleTable(system: SystemSection): void {
    if (system.item.hide_data || !props.dataBySystem[system.id]) {
        return;
    }

    const next = !isTableOpen(system);
    tableOpenBySystem[system.id] = next;

    if (next) {
        compactBlockIds[system.id] = false;
    }
}

function handleBlockHeaderClick(
    event: MouseEvent,
    system: SystemSection,
): void {
    const target = event.target as HTMLElement;

    if (target.closest('button, a, input, select, textarea')) {
        return;
    }

    toggleTable(system);
}

function getSubSystems(
    system: SystemSection,
): Array<{ id: number; name: string; parameters: ReportTableParameter[] }> {
    const data = props.dataBySystem[system.id];

    if (!data || !data.parameters.length) {
        return [];
    }

    const map = new Map<
        number,
        { id: number; name: string; parameters: ReportTableParameter[] }
    >();

    for (const param of data.parameters) {
        const sid = param.monitored_system_id ?? system.id;
        const sname = param.system_name || system.name;

        if (!map.has(sid)) {
            map.set(sid, { id: sid, name: sname, parameters: [] });
        }

        map.get(sid)!.parameters.push(param);
    }

    return Array.from(map.values());
}

function isGroupedBlock(system: SystemSection): boolean {
    return getSubSystems(system).length > 1;
}

function getGroupMode(systemId: number): 'system' | 'parameter' {
    return groupModeByBlock[systemId] ?? 'system';
}

function setGroupMode(systemId: number, mode: 'system' | 'parameter'): void {
    groupModeByBlock[systemId] = mode;
}

function setViewMode(mode: 'all' | 'none' | 'compact'): void {
    for (const system of props.systems) {
        if (mode === 'all') {
            compactBlockIds[system.id] = false;
            tableOpenBySystem[system.id] = true;
        } else if (mode === 'none') {
            compactBlockIds[system.id] = false;
            tableOpenBySystem[system.id] = false;
        } else {
            compactBlockIds[system.id] = true;
            tableOpenBySystem[system.id] = false;
        }
    }
}

function resetPreferences(): void {
    preferences.value = createDefaultPreferences();

    for (const system of props.systems) {
        delete compactBlockIds[system.id];
        delete tableOpenBySystem[system.id];
        delete groupModeByBlock[system.id];
    }
}

function getMetricsForBlock(
    system: SystemSection,
    filterSubSystemId?: number | null,
) {
    if (system.item.hide_data || !props.dataBySystem[system.id]) {
        return computeSystemStats(undefined);
    }

    return computeSystemStats(props.dataBySystem[system.id], filterSubSystemId);
}

const summaryRows = computed<SummarySystemRow[]>(() =>
    props.systems.map((system) => ({
        id: system.id,
        name: system.name,
        hideData: system.item.hide_data || !props.dataBySystem[system.id],
        metrics: getMetricsForBlock(system),
    })),
);

const summaryTotals = computed(() => {
    let total = 0;
    let eligible = 0;
    let deviations = 0;

    for (const row of summaryRows.value) {
        if (!row.hideData) {
            total += row.metrics.total;
            eligible += row.metrics.eligible;
            deviations += row.metrics.deviations;
        }
    }

    return { total, eligible, deviations };
});

const centralComments = computed<CentralCommentEntry[]>(() =>
    props.systems.flatMap((system) =>
        system.item.comments.map((comment) => ({
            id: comment.id,
            systemId: system.id,
            systemName: system.name,
            author: comment.author,
            isConsucal: comment.is_consucal,
            body: comment.body,
            createdAt: comment.created_at,
        })),
    ),
);

async function navigateToBlock(systemId: number): Promise<void> {
    const system = props.systems.find((s) => s.id === systemId);

    if (system) {
        compactBlockIds[system.id] = false;

        if (!system.item.hide_data && props.dataBySystem[system.id]) {
            tableOpenBySystem[system.id] = true;
        }
    }

    highlightedBlockId.value = systemId;
    await nextTick();
    document
        .getElementById(`report-block-${systemId}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });

    window.setTimeout(() => {
        if (highlightedBlockId.value === systemId) {
            highlightedBlockId.value = null;
        }
    }, 2000);
}

function openDeviations(
    system: SystemSection,
    subSystemId?: number | null,
    subSystemName?: string,
): void {
    const metrics = getMetricsForBlock(system, subSystemId);
    deviationsModal.value = {
        open: true,
        systemName: subSystemName || system.name,
        deviations: metrics.deviationEntries,
    };
}

function openChart(
    system: SystemSection,
    parameterId: number,
    type: 'line' | 'bar',
): void {
    const parameter = props.dataBySystem[system.id]?.parameters.find(
        (item) => item.id === parameterId,
    );
    chart.value = {
        open: true,
        title: `${system.name} · ${parameter?.name ?? 'Parâmetro'} (${type === 'bar' ? 'barras' : 'linha'})`,
        url: props.chartUrlGenerator(system.id, parameterId, type),
    };
}

function toggleReplies(systemId: number): void {
    repliesOpenByBlock[systemId] = !repliesOpenByBlock[systemId];
}

function toggleCompose(system: SystemSection): void {
    const next = !composingByBlock[system.id];
    composingByBlock[system.id] = next;

    if (next) {
        repliesOpenByBlock[system.id] = true;
    }
}

interface OwnInteractionEntry {
    systemId: number;
    systemName: string;
    comment: ReportComment;
}

const ownInteractions = computed<OwnInteractionEntry[]>(() => {
    const entries: OwnInteractionEntry[] = [];

    for (const system of props.systems) {
        for (const comment of system.item.comments) {
            if (!comment.is_own) {
                continue;
            }

            entries.push({
                systemId: system.id,
                systemName: system.name,
                comment,
            });
        }
    }

    return entries.sort((a, b) => b.comment.id - a.comment.id);
});

const pendingInteractions = computed(() =>
    ownInteractions.value.filter((entry) => !entry.comment.notified_at),
);

const pendingPanelOpen = ref(false);
const selectedInteractionIds = ref<number[]>(
    pendingInteractions.value.map((entry) => entry.comment.id),
);
const notifyConfirmOpen = ref(false);
const isNotifying = ref(false);
const notifyStatusMessage = ref('');
const notifyErrorMessage = ref('');

watch(
    () => pendingInteractions.value.map((entry) => entry.comment.id),
    (currentPendingIds, previousPendingIds = []) => {
        const pendingSet = new Set(currentPendingIds);
        const previousSet = new Set(previousPendingIds);
        const retained = selectedInteractionIds.value.filter((id) =>
            pendingSet.has(id),
        );
        const newlyAdded = currentPendingIds.filter(
            (id) => !previousSet.has(id),
        );

        selectedInteractionIds.value = [
            ...new Set([...retained, ...newlyAdded]),
        ];
    },
    { immediate: true },
);

const canNotifyInteractions = computed(
    () =>
        Boolean(props.notifyInteractionsUrl) &&
        props.report.status === 'completed' &&
        selectedInteractionIds.value.length > 0,
);

function toggleInteractionSelection(commentId: number): void {
    if (selectedInteractionIds.value.includes(commentId)) {
        selectedInteractionIds.value = selectedInteractionIds.value.filter(
            (id) => id !== commentId,
        );

        return;
    }

    selectedInteractionIds.value = [...selectedInteractionIds.value, commentId];
}

function formatInteractionDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return formatDateTimeBr(value);
    }

    const day = String(parsed.getDate()).padStart(2, '0');
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const year = parsed.getFullYear();
    const hours = String(parsed.getHours()).padStart(2, '0');
    const minutes = String(parsed.getMinutes()).padStart(2, '0');

    return `${day}/${month}/${year} · ${hours}h${minutes}`;
}

function confirmNotifyInteractions(): void {
    if (
        !props.notifyInteractionsUrl ||
        !canNotifyInteractions.value ||
        isNotifying.value
    ) {
        return;
    }

    notifyStatusMessage.value = '';
    notifyErrorMessage.value = '';
    isNotifying.value = true;

    router.post(
        props.notifyInteractionsUrl,
        {
            comment_ids: selectedInteractionIds.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                notifyConfirmOpen.value = false;
                notifyStatusMessage.value =
                    'E-mail de notificação enfileirado para os destinatários.';
            },
            onError: (errors) => {
                notifyConfirmOpen.value = false;
                notifyErrorMessage.value =
                    errors.comment_ids ??
                    errors.report ??
                    'Não foi possível enfileirar a notificação por e-mail.';
            },
            onFinish: () => {
                isNotifying.value = false;
            },
        },
    );
}

function publishComment(system: SystemSection): void {
    const body = commentBodies[system.item.id]?.trim();

    if (!body || isSubmittingComment[system.item.id]) {
        return;
    }

    isSubmittingComment[system.item.id] = true;
    const url = props.commentSubmitUrlGenerator(system.item.id);

    router.post(
        url,
        {
            report_item_id: system.item.id,
            body,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                commentBodies[system.item.id] = '';
                composingByBlock[system.id] = false;
                repliesOpenByBlock[system.id] = true;
                pendingPanelOpen.value = true;
                notifyStatusMessage.value = '';
                notifyErrorMessage.value = '';
            },
            onFinish: () => {
                isSubmittingComment[system.item.id] = false;
            },
        },
    );
}
</script>

<template>
    <div
        class="report-view-theme min-h-full w-full min-w-0 flex-1 bg-background text-foreground"
    >
        <div
            class="mx-auto grid w-full max-w-[1640px] min-w-0 gap-4 p-4 lg:p-6"
        >
            <!-- Área Administrativa (exclusiva da Prévia do Administrador) -->
            <section
                v-if="isPreview"
                aria-label="Área administrativa"
                class="rounded-xl border border-l-4 border-[var(--report-admin-border)] border-l-[var(--report-admin-accent)] bg-[var(--report-admin-bg)] p-4 text-[var(--report-admin-ink)] shadow-2xs"
            >
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-[var(--report-admin-border)] pb-2.5"
                >
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span
                            class="inline-flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-[var(--report-admin-ink)] uppercase"
                        >
                            <ShieldCheck class="size-4" />
                            ÁREA ADMINISTRATIVA
                        </span>
                        <span
                            class="border-l border-[var(--report-admin-border)] pl-2.5 text-[11px] text-[var(--report-admin-ink)] opacity-90"
                        >
                            Visível somente para administradores
                        </span>
                    </div>

                    <a
                        v-if="currentTeam"
                        :href="`/${currentTeam.slug}/relatorios/${report.id}`"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--report-admin-btn)] px-3 py-1.5 text-xs font-bold text-[var(--report-admin-btn-ink)] shadow-2xs transition hover:bg-[var(--report-admin-btn-hover)]"
                    >
                        <ArrowLeft class="size-3.5" />
                        <span>Ir para o modo de edição</span>
                    </a>
                </div>

                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4"
                >
                    <div
                        class="lg:border-r lg:border-[var(--report-admin-border)] lg:pr-3"
                    >
                        <div
                            class="text-[9.5px] font-semibold tracking-wider text-[var(--report-admin-ink)] uppercase opacity-80"
                        >
                            Status do relatório
                        </div>
                        <strong
                            class="mt-1 inline-flex items-center gap-1.5 text-[12.5px] font-semibold"
                            :class="
                                report.status === 'completed'
                                    ? 'text-emerald-700 dark:text-emerald-300'
                                    : 'text-[#a85a11] dark:text-[#f4bd79]'
                            "
                        >
                            <span
                                class="size-2 rounded-full"
                                :class="
                                    report.status === 'completed'
                                        ? 'bg-emerald-500'
                                        : 'bg-[#d58b32]'
                                "
                            />
                            {{
                                report.status === 'completed'
                                    ? 'Emitido / Finalizado'
                                    : 'Em elaboração'
                            }}
                        </strong>
                    </div>

                    <div
                        class="lg:border-r lg:border-[var(--report-admin-border)] lg:pr-3"
                    >
                        <div
                            class="text-[9.5px] font-semibold tracking-wider text-[var(--report-admin-ink)] uppercase opacity-80"
                        >
                            Responsável pela emissão
                        </div>
                        <strong class="mt-1 block text-[12.5px] font-semibold">
                            {{
                                report.issuer_name ||
                                recipient?.name ||
                                'Administrador Consucal'
                            }}
                        </strong>
                        <span class="text-[10.5px] opacity-80">
                            {{
                                recipient?.is_admin
                                    ? 'Administrador Consucal'
                                    : 'Emissor registrado'
                            }}
                        </span>
                    </div>

                    <div
                        class="lg:border-r lg:border-[var(--report-admin-border)] lg:pr-3"
                    >
                        <div
                            class="text-[9.5px] font-semibold tracking-wider text-[var(--report-admin-ink)] uppercase opacity-80"
                        >
                            Data e hora de emissão
                        </div>
                        <strong class="mt-1 block text-[12.5px] font-semibold">
                            {{
                                report.finished_at
                                    ? formatDateTimeBr(report.finished_at)
                                    : 'Pendente'
                            }}
                        </strong>
                    </div>

                    <div>
                        <div
                            class="text-[9.5px] font-semibold tracking-wider text-[var(--report-admin-ink)] uppercase opacity-80"
                        >
                            Destinatários e envios
                        </div>
                        <strong class="mt-1 block text-[12.5px] font-semibold">
                            {{ activeCount }} destinatário(s) ·
                            {{ sentRecipientsCount }} envio(s) ·
                            {{ accessedRecipientsCount }} acesso(s)
                            <template v-if="queuedRecipientsCount > 0">
                                ·
                                <span
                                    class="inline-flex items-center gap-1 text-sky-700 dark:text-sky-300"
                                >
                                    <Loader2 class="size-3 animate-spin" />
                                    {{ queuedRecipientsCount }} na fila
                                </span>
                            </template>
                        </strong>
                        <button
                            type="button"
                            class="mt-0.5 inline-flex items-center gap-1 text-[10.5px] font-semibold underline underline-offset-2 hover:opacity-80"
                            @click="emit('openRecipients')"
                        >
                            Gerenciar destinatários, envios e acessos
                        </button>
                    </div>
                </div>
            </section>

            <!-- Cabeçalho Institucional do Relatório -->
            <header
                class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 lg:px-6"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-5">
                        <div
                            class="shrink-0 rounded-md bg-white p-1.5 shadow-2xs"
                        >
                            <img
                                src="/logo.png"
                                alt="Consucal — consultoria química para caldeiras"
                                class="h-10 w-auto object-contain sm:h-11"
                            />
                        </div>

                        <div class="min-w-0 flex-1 border-l border-border pl-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1
                                    class="text-base font-bold tracking-tight text-foreground lg:text-lg"
                                >
                                    {{ report.title }}
                                </h1>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10.5px] font-semibold"
                                    :class="
                                        report.status === 'completed'
                                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                            : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                    "
                                >
                                    {{
                                        report.status === 'completed'
                                            ? 'Emitido'
                                            : 'Em Elaboração'
                                    }}
                                </span>
                            </div>

                            <div
                                class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground"
                            >
                                <span class="font-semibold text-foreground">
                                    {{ report.team_name }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <CalendarDays class="size-3.5" />
                                    <span class="font-semibold uppercase">
                                        Referência:
                                    </span>
                                    <strong class="text-foreground">
                                        {{
                                            formatDateBr(report.date_reference)
                                        }}
                                    </strong>
                                </span>
                                <button
                                    v-if="isPreview || recipients.length > 0"
                                    type="button"
                                    class="inline-flex items-center gap-1 border-l border-border pl-3 font-medium text-primary hover:underline"
                                    @click="
                                        isPreview
                                            ? emit('openRecipients')
                                            : (showRecipientsList =
                                                  !showRecipientsList)
                                    "
                                >
                                    <Users class="size-3.5" />
                                    <span>Destinatários</span>
                                    <span class="font-bold">
                                        ({{
                                            isPreview
                                                ? activeCount
                                                : recipients.length
                                        }})
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            (recipient && !isPreview) ||
                            report.team_logo_url ||
                            currentTeam?.logoUrl
                        "
                        class="ml-auto flex shrink-0 items-center gap-3"
                    >
                        <!-- Identificação do Destinatário (quando aplicável) -->
                        <div
                            v-if="recipient && !isPreview"
                            class="rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-3.5 py-2 text-xs"
                        >
                            <p
                                class="flex items-center gap-1.5 font-semibold text-emerald-800 dark:text-emerald-300"
                            >
                                <ShieldCheck class="size-3.5" />
                                <span>Acesso Individual do Destinatário</span>
                            </p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                {{ recipient.name }} · {{ recipient.email }}
                            </p>
                        </div>

                        <div
                            v-if="report.team_logo_url || currentTeam?.logoUrl"
                            class="flex h-12 max-w-28 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border/60 bg-white p-1.5 shadow-2xs"
                        >
                            <img
                                :src="
                                    report.team_logo_url ||
                                    currentTeam?.logoUrl ||
                                    ''
                                "
                                :alt="`Logo de ${report.team_name}`"
                                class="max-h-full max-w-full object-contain"
                            />
                        </div>
                    </div>
                </div>

                <!-- Lista pública de destinatários (quando expandida na visão cliente) -->
                <div
                    v-if="
                        !isPreview &&
                        showRecipientsList &&
                        recipients.length > 0
                    "
                    class="border-t border-border bg-muted/20 px-5 py-3"
                >
                    <div
                        class="mb-2 flex items-center justify-between text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <Mail class="size-3.5 text-primary" />
                            Destinatários do relatório ({{ recipients.length }})
                        </span>
                        <button
                            type="button"
                            class="text-primary hover:underline"
                            @click="showRecipientsList = false"
                        >
                            Fechar
                        </button>
                    </div>
                    <div
                        class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="dest in recipients"
                            :key="dest.email"
                            class="flex items-center justify-between gap-2 rounded-lg border border-border/70 bg-card px-3 py-2 text-xs"
                        >
                            <div class="min-w-0">
                                <strong class="block truncate font-semibold">
                                    {{ dest.name }}
                                </strong>
                                <span
                                    class="block truncate text-[11px] text-muted-foreground"
                                >
                                    {{ dest.email }}
                                </span>
                            </div>
                            <span
                                v-if="dest.last_sent_at"
                                class="shrink-0 rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300"
                            >
                                Enviado
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Barra Sticky: Ferramentas do Relatório -->
            <ClientReportToolbar
                v-if="systems.length > 0"
                :date-reference="report.date_reference"
                :preferences="preferences"
                :summary-rows="summaryRows"
                :summary-totals="summaryTotals"
                :central-comments="centralComments"
                @update:preferences="(prefs) => (preferences = prefs)"
                @set-view-mode="setViewMode"
                @reset-preferences="resetPreferences"
                @navigate-system="navigateToBlock"
            />

            <!-- Estado Vazio -->
            <div
                v-if="systems.length === 0"
                class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-card/60 py-16 text-center"
            >
                <p class="text-sm font-medium text-foreground">
                    Nenhum sistema habilitado ou com dados cadastrados neste
                    relatório.
                </p>
            </div>

            <!-- Blocos dos Sistemas -->
            <section
                v-for="system in systems"
                :id="`report-block-${system.id}`"
                :key="system.id"
                class="scroll-mt-28 overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xs transition duration-300"
                :class="
                    highlightedBlockId === system.id
                        ? 'ring-2 ring-primary'
                        : ''
                "
            >
                <!-- Faixa de Agrupamento (quando houver múltiplos sistemas no bloco) -->
                <div
                    v-if="isGroupedBlock(system)"
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--report-group-border)] bg-[var(--report-group-bg)] px-4 py-2 text-[11px] font-semibold text-[var(--report-group-ink)]"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <Link2 class="size-3.5" />
                        <span>Sistemas agrupados</span>
                    </span>

                    <div
                        role="group"
                        aria-label="Apresentação das tabelas agrupadas"
                        class="inline-flex items-center gap-1 rounded-md border border-[var(--report-group-border)] bg-transparent p-0.5 text-[11px]"
                    >
                        <button
                            type="button"
                            :aria-pressed="getGroupMode(system.id) === 'system'"
                            class="rounded px-2.5 py-1 font-medium transition"
                            :class="
                                getGroupMode(system.id) === 'system'
                                    ? 'bg-card font-bold text-foreground shadow-2xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="setGroupMode(system.id, 'system')"
                        >
                            Por sistema
                        </button>
                        <button
                            type="button"
                            :aria-pressed="
                                getGroupMode(system.id) === 'parameter'
                            "
                            class="rounded px-2.5 py-1 font-medium transition"
                            :class="
                                getGroupMode(system.id) === 'parameter'
                                    ? 'bg-card font-bold text-foreground shadow-2xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="setGroupMode(system.id, 'parameter')"
                        >
                            Por parâmetro
                        </button>
                    </div>
                </div>

                <!-- Cabeçalho(s) do(s) Sistema(s) (fundo azul suave + barra lateral azul + indicadores) -->
                <div
                    class="divide-y divide-[var(--report-soft-blue-border)] border-b border-[var(--report-soft-blue-border)] bg-[var(--report-soft-blue)]"
                >
                    <template v-if="isGroupedBlock(system)">
                        <div
                            v-for="sub in getSubSystems(system)"
                            :key="sub.id"
                            role="button"
                            tabindex="0"
                            :aria-expanded="isTableOpen(system)"
                            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 px-4 py-2.5 transition hover:bg-[var(--report-hover)]"
                            @click="handleBlockHeaderClick($event, system)"
                            @keydown.enter.prevent="toggleTable(system)"
                            @keydown.space.prevent="toggleTable(system)"
                        >
                            <div
                                class="flex min-w-0 flex-1 items-center gap-2.5"
                            >
                                <span
                                    class="h-6 w-1 shrink-0 rounded-full bg-primary"
                                />
                                <h2
                                    class="truncate text-[13px] font-semibold text-[var(--report-blue-dark)]"
                                >
                                    {{ sub.name }}
                                </h2>
                                <span
                                    v-if="system.item.is_stopped"
                                    class="rounded-full bg-amber-400/20 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:text-amber-300"
                                >
                                    Sistema parado
                                </span>
                            </div>

                            <div
                                class="flex flex-wrap items-center gap-3 text-xs tabular-nums"
                            >
                                <template
                                    v-if="
                                        getMetricsForBlock(system, sub.id)
                                            .total > 0
                                    "
                                >
                                    <span class="text-muted-foreground">
                                        <strong class="text-foreground">{{
                                            getMetricsForBlock(system, sub.id)
                                                .total
                                        }}</strong>
                                        dados ·
                                        <strong class="text-foreground">{{
                                            getMetricsForBlock(system, sub.id)
                                                .eligible
                                        }}</strong>
                                        avaliáveis
                                    </span>

                                    <button
                                        v-if="
                                            getMetricsForBlock(system, sub.id)
                                                .deviations > 0
                                        "
                                        type="button"
                                        title="Consultar resultados em desvio"
                                        class="inline-flex items-center gap-1 font-bold text-red-600 underline underline-offset-2 hover:opacity-80 dark:text-red-400"
                                        @click.stop="
                                            openDeviations(
                                                system,
                                                sub.id,
                                                sub.name,
                                            )
                                        "
                                    >
                                        <AlertTriangle class="size-3.5" />
                                        <span>
                                            {{
                                                getMetricsForBlock(
                                                    system,
                                                    sub.id,
                                                ).deviations
                                            }}
                                            {{
                                                getMetricsForBlock(
                                                    system,
                                                    sub.id,
                                                ).deviations === 1
                                                    ? 'desvio'
                                                    : 'desvios'
                                            }}
                                            ({{
                                                getMetricsForBlock(
                                                    system,
                                                    sub.id,
                                                ).deviationPct
                                            }})
                                        </span>
                                    </button>
                                    <span
                                        v-else-if="
                                            getMetricsForBlock(system, sub.id)
                                                .eligible > 0
                                        "
                                        class="inline-flex items-center gap-1 font-semibold text-emerald-700 dark:text-emerald-400"
                                    >
                                        <CheckCircle2 class="size-3.5" />
                                        <span>0 desvios (0,0%)</span>
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        Sem resultados avaliáveis
                                    </span>
                                </template>
                                <span v-else class="text-muted-foreground">
                                    Sem dados
                                </span>

                                <button
                                    type="button"
                                    :aria-expanded="isTableOpen(system)"
                                    :title="
                                        isTableOpen(system)
                                            ? 'Ocultar tabela'
                                            : 'Exibir tabela'
                                    "
                                    class="inline-flex size-7 items-center justify-center rounded-md border border-border bg-card/80 text-primary transition hover:bg-card"
                                    @click.stop="toggleTable(system)"
                                >
                                    <ChevronDown
                                        class="size-4 transition-transform duration-200"
                                        :class="
                                            isTableOpen(system)
                                                ? 'rotate-180'
                                                : ''
                                        "
                                    />
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Sistema Individual (ou Tabela Oculta) -->
                    <div
                        v-else
                        role="button"
                        tabindex="0"
                        :aria-expanded="isTableOpen(system)"
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 transition"
                        :class="
                            !system.item.hide_data && dataBySystem[system.id]
                                ? 'cursor-pointer hover:bg-[var(--report-hover)]'
                                : ''
                        "
                        @click="handleBlockHeaderClick($event, system)"
                        @keydown.enter.prevent="toggleTable(system)"
                        @keydown.space.prevent="toggleTable(system)"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-2.5">
                            <span
                                class="h-6 w-1 shrink-0 rounded-full bg-primary"
                            />
                            <h2
                                class="truncate text-[13px] font-semibold text-[var(--report-blue-dark)]"
                            >
                                {{ system.name }}
                            </h2>
                            <span
                                v-if="system.item.is_stopped"
                                class="rounded-full bg-amber-400/20 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:text-amber-300"
                            >
                                Sistema parado
                            </span>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-3 text-xs tabular-nums"
                        >
                            <template
                                v-if="
                                    system.item.hide_data ||
                                    !dataBySystem[system.id]
                                "
                            >
                                <span
                                    class="text-[11px] font-medium text-muted-foreground"
                                >
                                    Tabela oculta no relatório
                                </span>
                            </template>

                            <template
                                v-else-if="getMetricsForBlock(system).total > 0"
                            >
                                <span class="text-muted-foreground">
                                    <strong class="text-foreground">{{
                                        getMetricsForBlock(system).total
                                    }}</strong>
                                    dados ·
                                    <strong class="text-foreground">{{
                                        getMetricsForBlock(system).eligible
                                    }}</strong>
                                    avaliáveis
                                </span>

                                <button
                                    v-if="
                                        getMetricsForBlock(system).deviations >
                                        0
                                    "
                                    type="button"
                                    title="Consultar resultados em desvio"
                                    class="inline-flex items-center gap-1 font-bold text-red-600 underline underline-offset-2 hover:opacity-80 dark:text-red-400"
                                    @click.stop="openDeviations(system)"
                                >
                                    <AlertTriangle class="size-3.5" />
                                    <span>
                                        {{
                                            getMetricsForBlock(system)
                                                .deviations
                                        }}
                                        {{
                                            getMetricsForBlock(system)
                                                .deviations === 1
                                                ? 'desvio'
                                                : 'desvios'
                                        }}
                                        ({{
                                            getMetricsForBlock(system)
                                                .deviationPct
                                        }})
                                    </span>
                                </button>
                                <span
                                    v-else-if="
                                        getMetricsForBlock(system).eligible > 0
                                    "
                                    class="inline-flex items-center gap-1 font-semibold text-emerald-700 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="size-3.5" />
                                    <span>0 desvios (0,0%)</span>
                                </span>
                                <span v-else class="text-muted-foreground">
                                    Sem resultados avaliáveis
                                </span>
                            </template>

                            <span v-else class="text-muted-foreground">
                                Sem dados
                            </span>

                            <button
                                v-if="
                                    !system.item.hide_data &&
                                    dataBySystem[system.id]
                                "
                                type="button"
                                :aria-expanded="isTableOpen(system)"
                                :title="
                                    isTableOpen(system)
                                        ? 'Ocultar tabela'
                                        : 'Exibir tabela'
                                "
                                class="inline-flex size-7 items-center justify-center rounded-md border border-border bg-card/80 text-primary transition hover:bg-card"
                                @click.stop="toggleTable(system)"
                            >
                                <ChevronDown
                                    class="size-4 transition-transform duration-200"
                                    :class="
                                        isTableOpen(system) ? 'rotate-180' : ''
                                    "
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabelas Analíticas do Sistema (ocultas se compact mode ou hide_data) -->
                <div
                    v-if="!compactBlockIds[system.id] && isTableOpen(system)"
                    class="space-y-4 border-t border-border p-4"
                >
                    <!-- Modo Comparativo Por Parâmetro -->
                    <ReportHorizontalDataTable
                        v-if="
                            isGroupedBlock(system) &&
                            getGroupMode(system.id) === 'parameter'
                        "
                        :parameters="dataBySystem[system.id]?.parameters ?? []"
                        :rows="dataBySystem[system.id]?.rows ?? []"
                        :averages="dataBySystem[system.id]?.averages"
                        :visible-categories="preferences.categories"
                        :show-tags="preferences.showTags"
                        :show-mean="preferences.showMean"
                        :show-charts="preferences.showCharts"
                        :show-system-column="true"
                        :table-font-size="preferences.tableFont"
                        :hide-grouped-filter="true"
                        @chart="
                            (paramId, type) => openChart(system, paramId, type)
                        "
                    />

                    <!-- Modo Padrão Por Sistema (uma tabela por integrante quando agrupado) -->
                    <template v-else-if="isGroupedBlock(system)">
                        <div
                            v-for="sub in getSubSystems(system)"
                            :key="`table-${sub.id}`"
                            class="space-y-2"
                        >
                            <div
                                class="border-l-3 border-primary pl-2.5 text-xs font-bold text-foreground"
                            >
                                {{ sub.name }}
                            </div>
                            <ReportHorizontalDataTable
                                :parameters="sub.parameters"
                                :rows="dataBySystem[system.id]?.rows ?? []"
                                :averages="dataBySystem[system.id]?.averages"
                                :visible-categories="preferences.categories"
                                :show-tags="preferences.showTags"
                                :show-mean="preferences.showMean"
                                :show-charts="preferences.showCharts"
                                :table-font-size="preferences.tableFont"
                                :hide-grouped-filter="true"
                                @chart="
                                    (paramId, type) =>
                                        openChart(system, paramId, type)
                                "
                            />
                        </div>
                    </template>

                    <!-- Sistema Individual -->
                    <ReportHorizontalDataTable
                        v-else
                        :parameters="dataBySystem[system.id]?.parameters ?? []"
                        :rows="dataBySystem[system.id]?.rows ?? []"
                        :averages="dataBySystem[system.id]?.averages"
                        :visible-categories="preferences.categories"
                        :show-tags="preferences.showTags"
                        :show-mean="preferences.showMean"
                        :show-charts="preferences.showCharts"
                        :table-font-size="preferences.tableFont"
                        :hide-grouped-filter="true"
                        @chart="
                            (paramId, type) => openChart(system, paramId, type)
                        "
                    />
                </div>

                <!-- Considerações Consucal (ocultas apenas no modo "Ocultar todo o conteúdo") -->
                <div
                    v-if="!compactBlockIds[system.id]"
                    class="border-t border-border p-4 lg:px-5"
                >
                    <div
                        class="mb-2 flex items-center gap-2 text-[11.5px] font-semibold text-primary"
                    >
                        <ClipboardList class="size-4" />
                        <h3>Considerações Consucal</h3>
                    </div>

                    <div
                        v-if="system.item.comment"
                        :style="{ fontSize: `${preferences.technicalFont}px` }"
                        class="leading-relaxed"
                    >
                        <ReportCommentContent
                            :html="system.item.comment"
                            :document="system.item.document"
                            :charts="system.item.charts"
                            :team-slug="currentTeam?.slug"
                        />
                    </div>
                    <p v-else class="text-xs text-muted-foreground italic">
                        Sem considerações registradas para este bloco.
                    </p>
                </div>

                <!-- Comentários / Respostas (Faixa compacta expansível) -->
                <div
                    v-if="!compactBlockIds[system.id]"
                    class="border-t border-[var(--report-interaction-border)] bg-[var(--report-interaction-bg)] px-4 py-2.5 lg:px-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <button
                            type="button"
                            :aria-expanded="
                                Boolean(repliesOpenByBlock[system.id])
                            "
                            class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-[var(--report-interaction-ink)] transition hover:text-primary"
                            @click="toggleReplies(system.id)"
                        >
                            <MessageSquare
                                class="size-3.5 text-[var(--report-interaction-ink)] opacity-80"
                            />
                            <span>Comentários / Respostas</span>
                            <strong class="tabular-nums">
                                ({{ system.item.comments.length }})
                            </strong>
                            <ChevronDown
                                class="size-3.5 text-[var(--report-interaction-ink)] opacity-80 transition-transform duration-200"
                                :class="
                                    repliesOpenByBlock[system.id]
                                        ? 'rotate-180'
                                        : ''
                                "
                            />
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-[var(--report-interaction-border)] bg-card px-2.5 py-1 text-[11px] font-semibold text-[var(--report-interaction-ink)] transition hover:bg-[var(--report-interaction-hover)]"
                            @click="toggleCompose(system)"
                        >
                            <Plus class="size-3" />
                            <span>Comentar</span>
                        </button>
                    </div>

                    <div
                        v-if="repliesOpenByBlock[system.id]"
                        class="mt-3 space-y-2"
                    >
                        <div
                            v-if="system.item.comments.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            Nenhuma interação registrada.
                        </div>

                        <div
                            v-for="comment in system.item.comments"
                            :key="comment.id"
                            class="rounded-r-lg border border-l-2 border-border/80 bg-card p-3 text-xs"
                            :class="
                                comment.is_consucal
                                    ? 'border-l-primary'
                                    : 'border-l-slate-400'
                            "
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-1.5">
                                    <strong
                                        class="font-semibold text-foreground"
                                    >
                                        {{ comment.author }}
                                    </strong>
                                    <span
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            comment.is_consucal
                                                ? 'bg-primary/10 text-primary'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{
                                            comment.is_consucal
                                                ? 'Consucal'
                                                : 'Cliente'
                                        }}
                                    </span>
                                </div>
                                <time class="text-[11px] text-muted-foreground">
                                    {{ formatDateTimeBr(comment.created_at) }}
                                </time>
                            </div>
                            <p
                                class="mt-1.5 whitespace-pre-wrap text-foreground/90"
                            >
                                {{ comment.body }}
                            </p>
                        </div>
                    </div>

                    <form
                        v-if="composingByBlock[system.id]"
                        class="mt-3 space-y-2"
                        @submit.prevent="publishComment(system)"
                    >
                        <textarea
                            v-model="commentBodies[system.item.id]"
                            rows="3"
                            maxlength="5000"
                            placeholder="Escreva um comentário sobre este bloco..."
                            class="w-full rounded-lg border border-input bg-card p-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                        />
                        <div class="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted"
                                @click="composingByBlock[system.id] = false"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="
                                    isSubmittingComment[system.item.id] ||
                                    !commentBodies[system.item.id]?.trim()
                                "
                                class="rounded-lg bg-primary px-3.5 py-1.5 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                            >
                                {{
                                    isSubmittingComment[system.item.id]
                                        ? 'Enviando…'
                                        : 'Registrar comentário'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- Modal de Resultados em Desvio -->
        <ClientReportDeviationsModal
            :open="deviationsModal.open"
            :system-name="deviationsModal.systemName"
            :deviations="deviationsModal.deviations"
            @close="deviationsModal.open = false"
        />

        <!-- Modal de Gráfico de Parâmetro -->
        <ParameterChartModal
            :open="chart.open"
            :url="chart.url"
            :title="chart.title"
            @close="chart.open = false"
        />

        <!-- Painel flutuante: Minhas interações -->
        <aside
            v-if="ownInteractions.length > 0"
            data-test="my-interactions-panel"
            aria-label="Minhas interações"
            class="fixed right-4 bottom-3 z-40 w-[336px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-purple-300/80 bg-card text-foreground shadow-2xl dark:border-purple-300/35"
        >
            <button
                type="button"
                data-test="my-interactions-toggle"
                :aria-expanded="pendingPanelOpen"
                class="flex w-full items-center justify-between gap-2 bg-purple-100 px-3.5 py-2.5 text-left text-xs font-semibold text-purple-950 transition hover:bg-purple-200/80 dark:bg-[#c4a8e8] dark:text-[#1e1530] dark:hover:bg-[#cfb5ef]"
                @click="pendingPanelOpen = !pendingPanelOpen"
            >
                <span class="flex items-center gap-2">
                    <MessageSquareText class="size-3.5 shrink-0 opacity-85" />
                    <span>Minhas interações</span>
                </span>

                <span class="flex items-center gap-2">
                    <span
                        data-test="my-interactions-badge"
                        class="rounded-md bg-purple-950/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums dark:bg-[#2a1d40]/90 dark:text-purple-100"
                    >
                        {{
                            pendingInteractions.length > 0
                                ? `${pendingInteractions.length} a notificar`
                                : 'Nenhuma pendente'
                        }}
                    </span>
                    <ChevronDown
                        class="size-3.5 shrink-0 transition-transform duration-200"
                        :class="pendingPanelOpen ? '' : 'rotate-180'"
                    />
                </span>
            </button>

            <div v-if="pendingPanelOpen" class="border-t border-border">
                <div
                    class="max-h-56 divide-y divide-border overflow-y-auto px-3.5 py-1.5"
                >
                    <label
                        v-for="entry in ownInteractions"
                        :key="entry.comment.id"
                        :data-test="`my-interaction-item-${entry.comment.id}`"
                        class="flex items-start gap-2.5 py-2 text-xs"
                        :class="
                            entry.comment.notified_at
                                ? 'opacity-70'
                                : 'cursor-pointer'
                        "
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5 size-3.5 shrink-0 rounded border-border accent-primary"
                            :disabled="Boolean(entry.comment.notified_at)"
                            :checked="
                                Boolean(entry.comment.notified_at) ||
                                selectedInteractionIds.includes(
                                    entry.comment.id,
                                )
                            "
                            @change="
                                toggleInteractionSelection(entry.comment.id)
                            "
                        />

                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold text-foreground">
                                {{ entry.systemName }}
                            </div>
                            <div
                                class="mt-0.5 line-clamp-2 break-words text-muted-foreground"
                            >
                                {{ entry.comment.body }}
                            </div>
                            <div class="mt-1 text-[11px] text-muted-foreground">
                                {{
                                    formatInteractionDateTime(
                                        entry.comment.created_at,
                                    )
                                }}
                                ·
                                {{
                                    entry.comment.notified_at
                                        ? `Notificado em ${formatInteractionDateTime(entry.comment.notified_at)}`
                                        : 'Pendente de notificação'
                                }}
                            </div>
                        </div>
                    </label>
                </div>

                <div
                    class="space-y-2 border-t border-border bg-muted/20 px-3.5 py-2.5"
                >
                    <p class="text-[11px] leading-snug text-muted-foreground">
                        Reenviar o link do relatório aos destinatários
                        cadastrados, com aviso das interações selecionadas.
                    </p>

                    <p
                        v-if="report.status !== 'completed'"
                        class="text-[11px] font-medium text-amber-600 dark:text-amber-400"
                    >
                        Finalize o relatório para liberar a notificação por
                        e-mail.
                    </p>

                    <p
                        v-if="notifyStatusMessage"
                        data-test="my-interactions-status"
                        class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        {{ notifyStatusMessage }}
                    </p>

                    <p
                        v-if="notifyErrorMessage"
                        data-test="my-interactions-error"
                        class="text-[11px] font-medium text-destructive"
                    >
                        {{ notifyErrorMessage }}
                    </p>

                    <Button
                        type="button"
                        size="sm"
                        data-test="my-interactions-notify-btn"
                        class="w-full bg-purple-600 text-white hover:bg-purple-700 dark:bg-[#c4a8e8] dark:text-[#1e1530] dark:hover:bg-[#cfb5ef]"
                        :disabled="!canNotifyInteractions || isNotifying"
                        @click="notifyConfirmOpen = true"
                    >
                        <Loader2
                            v-if="isNotifying"
                            class="size-3.5 animate-spin"
                        />
                        <span>Reenviar relatório por e-mail</span>
                    </Button>
                </div>
            </div>
        </aside>

        <!-- Confirmação de envio de notificação de interações -->
        <Dialog v-model:open="notifyConfirmOpen">
            <DialogContent
                class="sm:max-w-md"
                data-test="notify-interactions-confirm-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Reenviar relatório por e-mail</DialogTitle>
                    <DialogDescription>
                        Será enviado um e-mail com o link do relatório para os
                        destinatários cadastrados destacando
                        {{ selectedInteractionIds.length }}
                        {{
                            selectedInteractionIds.length === 1
                                ? 'interação selecionada'
                                : 'interações selecionadas'
                        }}.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isNotifying"
                        @click="notifyConfirmOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        data-test="notify-interactions-confirm-btn"
                        :disabled="isNotifying"
                        @click="confirmNotifyInteractions"
                    >
                        <Loader2
                            v-if="isNotifying"
                            class="size-3.5 animate-spin"
                        />
                        <span>Confirmar envio</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
