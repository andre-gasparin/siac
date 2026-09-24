<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import FloatingConsolidatedReportAgent from '@/features/reports/components/FloatingConsolidatedReportAgent.vue';
import type { ConsolidatedSuggestion } from '@/features/reports/components/FloatingConsolidatedReportAgent.vue';
import ParameterChartModal from '@/features/reports/components/ParameterChartModal.vue';
import ReportGroupSystemModal from '@/features/reports/components/ReportGroupSystemModal.vue';
import ReportHistoryModal from '@/features/reports/components/ReportHistoryModal.vue';
import ReportRecipientsModal from '@/features/reports/components/ReportRecipientsModal.vue';
import ReportShowHeader from '@/features/reports/components/ReportShowHeader.vue';
import ReportSystemSection from '@/features/reports/components/ReportSystemSection.vue';
import {
    finalize,
    resend,
    show as showReport,
    store as storeReport,
} from '@/routes/reports';
import { destroy as revokeRecipient } from '@/routes/reports/recipients';
import { update as updateSuggestion } from '@/routes/reports/suggestions';
import {
    chart as chartRoute,
    data as dataRoute,
    update as updateSystem,
} from '@/routes/reports/systems';
import type { Team } from '@/shared/types';

interface ReportItem {
    id: number;
    parent_report_item_id?: number | null;
    parent_monitored_system_id?: number | null;
    show_data_results: boolean;
    hide_data: boolean;
    is_stopped: boolean;
    comment: string | null;
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
    report: {
        id: number;
        title: string;
        status: string;
        date_reference: string;
        finished_at: string | null;
        emailed_at: string | null;
        email_count: number;
    };
    systems: SystemSection[];
    recipients: Array<{
        id: number;
        user_id: number | null;
        name: string;
        email: string;
        revoked_at: string | null;
        last_sent_at: string | null;
        send_count: number;
    }>;
    intendedRecipients: Array<{
        user_id: number;
        name: string;
        email: string;
        recipient_id: number | null;
        last_sent_at: string | null;
        send_count: number;
    }>;
    activities: Array<{
        id: number;
        action: string;
        actor: string;
        metadata: Record<string, unknown> | null;
        created_at: string;
    }>;
    currentTeam: Team;
    existingReportsByDate?: Record<string, number>;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Tabela de dados',
            href: `/${props.currentTeam.slug}/tabela-dados`,
        },
        { title: props.report.title, href: '#' },
    ],
});

const pendingDate = ref(props.report.date_reference);
const datePrompt = ref<{
    open: boolean;
    date: string;
    exists: boolean;
    targetReportId?: number;
} | null>(null);

function onDateInputChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const chosenDate = input.value;
    pendingDate.value = chosenDate;

    if (!chosenDate || chosenDate === props.report.date_reference) {
        datePrompt.value = null;

        return;
    }

    const existingId = props.existingReportsByDate?.[chosenDate];

    if (existingId) {
        datePrompt.value = {
            open: true,
            date: chosenDate,
            exists: true,
            targetReportId: existingId,
        };
    } else {
        datePrompt.value = {
            open: true,
            date: chosenDate,
            exists: false,
        };
    }
}

function cancelDatePrompt() {
    pendingDate.value = props.report.date_reference;
    datePrompt.value = null;
}

function confirmDatePrompt() {
    if (!datePrompt.value) {
        return;
    }

    if (datePrompt.value.exists && datePrompt.value.targetReportId) {
        router.visit(
            showReport.url({
                current_team: props.currentTeam.slug,
                report: datePrompt.value.targetReportId,
            }),
        );
    } else {
        router.post(storeReport.url({ current_team: props.currentTeam.slug }), {
            date_reference: datePrompt.value.date,
        });
    }

    datePrompt.value = null;
}

const sections = ref<SystemSection[]>(
    props.systems.map((system) => ({
        ...system,
        item: system.item
            ? {
                  ...system.item,
                  comments: system.item.comments.map((comment) => ({
                      ...comment,
                  })),
                  suggestions: system.item.suggestions.map((suggestion) => ({
                      ...suggestion,
                  })),
              }
            : null,
    })),
);
const resolvingSuggestions = reactive<Record<number, boolean>>({});

watch(
    () => props.systems,
    (newSystems) => {
        sections.value = newSystems.map((system) => ({
            ...system,
            item: system.item
                ? {
                      ...system.item,
                      comments: system.item.comments.map((comment) => ({
                          ...comment,
                      })),
                      suggestions: system.item.suggestions.map(
                          (suggestion) => ({
                              ...suggestion,
                          }),
                      ),
                  }
                : null,
        }));
    },
    { deep: true },
);

const topLevelSections = computed(() =>
    sections.value.filter((system) => !system.item?.parent_monitored_system_id),
);

const enabledSections = computed(() =>
    topLevelSections.value
        .filter((system) => system.item?.show_data_results)
        .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id),
);

const disabledSections = computed(() =>
    topLevelSections.value
        .filter((system) => !system.item?.show_data_results)
        .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id),
);

const sortedSections = computed(() => [
    ...enabledSections.value,
    ...disabledSections.value,
]);

function getGroupedChildren(systemId: number): SystemSection[] {
    return sections.value.filter(
        (system) => system.item?.parent_monitored_system_id === systemId,
    );
}

function getCombinedTitle(system: SystemSection): string {
    const children = getGroupedChildren(system.id);

    if (children.length === 0) {
        return system.name;
    }

    const names = [system.name, ...children.map((child) => child.name)];

    if (names.length === 2) {
        return `${names[0]} e ${names[1]}`;
    }

    const last = names.pop();

    return `${names.join(', ')} e ${last}`;
}

function availableTargetSystems(system: SystemSection): SystemSection[] {
    return enabledSections.value.filter((s) => s.id !== system.id);
}

function canGroup(system: SystemSection): boolean {
    if (!system.item?.show_data_results) {
        return false;
    }

    return availableTargetSystems(system).length > 0;
}

const groupModal = reactive<{
    open: boolean;
    sourceSystem: SystemSection | null;
    targetSystemId: number | null;
}>({
    open: false,
    sourceSystem: null,
    targetSystemId: null,
});

function handleGroupClick(system: SystemSection): void {
    const targets = availableTargetSystems(system);

    if (targets.length === 0) {
        return;
    }

    groupModal.open = true;
    groupModal.sourceSystem = system;
    groupModal.targetSystemId = targets[0].id;
}

function confirmGroupSelection(): void {
    if (groupModal.sourceSystem && groupModal.targetSystemId) {
        const source = groupModal.sourceSystem;
        const targetId = groupModal.targetSystemId;
        const hasComment = Boolean(
            source.item?.comment &&
            source.item.comment.replace(/<[^>]+>/g, '').trim(),
        );

        void groupSystem(source, targetId, hasComment);
    }

    groupModal.open = false;
    groupModal.sourceSystem = null;
    groupModal.targetSystemId = null;
}

async function groupSystem(
    system: SystemSection,
    parentSystemId: number | null,
    clearComment: boolean,
): Promise<void> {
    try {
        const response = await fetch(
            updateSystem.url({
                current_team: props.currentTeam.slug,
                report: props.report.id,
            }),
            {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    system_id: system.id,
                    parent_system_id: parentSystemId,
                    clear_comment: clearComment,
                }),
            },
        );

        if (!response.ok) {
            return;
        }

        const result = await response.json();

        system.item ??= {
            id: result.item_id,
            parent_report_item_id: null,
            parent_monitored_system_id: null,
            show_data_results: true,
            hide_data: false,
            is_stopped: false,
            comment: null,
            updated_at: result.updated_at,
            comments: [],
            suggestions: [],
        };

        system.item.parent_report_item_id =
            result.parent_report_item_id ?? null;
        system.item.parent_monitored_system_id = parentSystemId;
        system.item.show_data_results = true;

        if (clearComment) {
            system.item.comment = null;
        }

        if (parentSystemId !== null) {
            const parent = sections.value.find((s) => s.id === parentSystemId);

            if (parent) {
                await loadSystem(parent, true);
            }
        } else {
            await loadSystem(system, true);
        }
    } catch {
        // Handle error gracefully
    }
}

const defaultEndDate = props.report.date_reference;
const defaultStartDate = (() => {
    const end = new Date(`${props.report.date_reference}T12:00:00`);
    end.setDate(end.getDate() - 6);

    return end.toISOString().split('T')[0];
})();

const systemDateFilters = reactive<
    Record<number, { start_date: string; end_date: string }>
>({});

function getSystemDates(systemId: number) {
    if (!systemDateFilters[systemId]) {
        systemDateFilters[systemId] = {
            start_date: defaultStartDate,
            end_date: defaultEndDate,
        };
    }

    return systemDateFilters[systemId];
}

const systemData = reactive<Record<number, ReportData>>({});
const loadingSystems = reactive<Record<number, boolean>>({});
const historyOpen = ref(false);
const recipientsOpen = ref(false);
const agentOpen = ref(false);
const highlightedSystemId = ref<number | null>(null);
const chart = ref<{ open: boolean; url: string; title: string }>({
    open: false,
    url: '',
    title: '',
});

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

async function loadSystem(
    system: SystemSection,
    force = false,
    datesOverride?: { start_date: string; end_date: string },
) {
    if (datesOverride) {
        systemDateFilters[system.id] = { ...datesOverride };
    }

    if ((systemData[system.id] && !force) || loadingSystems[system.id]) {
        return;
    }

    loadingSystems[system.id] = true;
    const dates = getSystemDates(system.id);

    try {
        const url = new URL(
            dataRoute.url({
                current_team: props.currentTeam.slug,
                report: props.report.id,
                system: system.id,
            }),
            window.location.origin,
        );
        url.searchParams.set('start_date', dates.start_date);
        url.searchParams.set('end_date', dates.end_date);

        const response = await fetch(url.toString(), {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            systemData[system.id] = await response.json();
        }
    } finally {
        loadingSystems[system.id] = false;
    }
}

async function toggleSystem(system: SystemSection, checked: boolean) {
    const response = await fetch(
        updateSystem.url({
            current_team: props.currentTeam.slug,
            report: props.report.id,
        }),
        {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                system_id: system.id,
                show_data_results: checked,
            }),
        },
    );

    if (!response.ok) {
        return;
    }

    const result = await response.json();

    system.item ??= {
        id: result.item_id,
        show_data_results: false,
        hide_data: false,
        is_stopped: false,
        comment: null,
        updated_at: result.updated_at,
        comments: [],
        suggestions: [],
    };
    system.item.show_data_results = result.show_data_results;

    if (result.show_data_results) {
        await loadSystem(system);
    }
}

function finish() {
    router.post(
        finalize.url({
            current_team: props.currentTeam.slug,
            report: props.report.id,
        }),
    );
}

function sendAgain() {
    router.post(
        resend.url({
            current_team: props.currentTeam.slug,
            report: props.report.id,
        }),
    );
}

function addAgentSuggestions(suggestions: ConsolidatedSuggestion[]) {
    for (const suggestion of suggestions) {
        const system = sections.value.find(
            (section) => section.id === suggestion.system_id,
        );

        if (
            system?.item &&
            !system.item.suggestions.some((item) => item.id === suggestion.id)
        ) {
            system.item.suggestions.push(suggestion);
        }
    }
}

async function focusSystem(systemId: number) {
    highlightedSystemId.value = systemId;
    await nextTick();
    document
        .querySelector(`[data-report-system-id="${systemId}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    window.setTimeout(() => {
        if (highlightedSystemId.value === systemId) {
            highlightedSystemId.value = null;
        }
    }, 2400);
}

async function resolveSuggestion(id: number, decision: 'accept' | 'reject') {
    if (resolvingSuggestions[id]) {
        return;
    }

    resolvingSuggestions[id] = true;

    for (const section of sections.value) {
        if (!section.item?.suggestions) {
            continue;
        }

        const index = section.item.suggestions.findIndex(
            (item) => item.id === id,
        );

        if (index !== -1) {
            const suggestion = section.item.suggestions[index];

            if (decision === 'accept' && section.item) {
                if (!section.item.comment || !suggestion.original_text) {
                    section.item.comment = `<p>${suggestion.replacement_text}</p>`;
                } else {
                    section.item.comment = section.item.comment.replace(
                        suggestion.original_text,
                        suggestion.replacement_text,
                    );
                }
            }

            section.item.suggestions.splice(index, 1);
            break;
        }
    }

    try {
        await fetch(
            updateSuggestion.url({
                current_team: props.currentTeam.slug,
                report: props.report.id,
                suggestion: id,
            }),
            {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ decision }),
            },
        );

        router.reload();
    } catch {
        router.reload();
    } finally {
        delete resolvingSuggestions[id];
    }
}

function openChart(
    system: SystemSection,
    parameterId: number,
    type: 'line' | 'bar',
) {
    const parameter = systemData[system.id]?.parameters.find(
        (item) => item.id === parameterId,
    );
    chart.value = {
        open: true,
        title: `${system.name} · ${parameter?.name ?? 'Parâmetro'} (${type === 'bar' ? 'barras' : 'linha'})`,
        url: chartRoute.url(
            {
                current_team: props.currentTeam.slug,
                report: props.report.id,
                system: system.id,
            },
            { query: { parameter_id: parameterId, type } },
        ),
    };
}

onMounted(() =>
    sections.value
        .filter(
            (system) =>
                system.item?.show_data_results && !system.item.hide_data,
        )
        .forEach((system) => loadSystem(system)),
);
</script>

<template>
    <Head :title="report.title" />

    <div class="mx-auto grid w-full max-w-[1600px] gap-6 p-4 lg:p-7">
        <!-- Cabeçalho Executivo -->
        <ReportShowHeader
            :report="report"
            :current-team="currentTeam"
            :sections="sortedSections"
            :intended-recipients-count="intendedRecipients.length"
            :pending-date="pendingDate"
            :date-prompt="datePrompt"
            @date-change="onDateInputChange"
            @cancel-date-prompt="cancelDatePrompt"
            @confirm-date-prompt="confirmDatePrompt"
            @open-history="historyOpen = true"
            @open-recipients="recipientsOpen = true"
            @send-again="sendAgain"
            @finish="finish"
        />

        <!-- Lista de Seções de Sistemas -->
        <ReportSystemSection
            v-for="system in sortedSections"
            :key="system.id"
            :system="system"
            :grouped-children="getGroupedChildren(system.id)"
            :combined-title="getCombinedTitle(system)"
            :can-group="canGroup(system)"
            :is-highlighted="highlightedSystemId === system.id"
            :system-dates="getSystemDates(system.id)"
            :is-loading-data="loadingSystems[system.id] || false"
            :system-data="systemData[system.id]"
            :current-team="currentTeam"
            :systems="systems"
            :report-date-reference="report.date_reference"
            :resolving-suggestions="resolvingSuggestions"
            @ungroup-child="(child) => groupSystem(child, null, false)"
            @open-group-modal="handleGroupClick"
            @toggle-system="toggleSystem"
            @load-data="loadSystem"
            @chart="openChart"
            @resolve-suggestion="resolveSuggestion"
            @editor-saved="router.reload()"
        />
    </div>

    <!-- Modal de Gráfico do Parâmetro -->
    <ParameterChartModal
        :open="chart.open"
        :url="chart.url"
        :title="chart.title"
        @close="chart.open = false"
    />

    <!-- Modal de Histórico do Relatório -->
    <ReportHistoryModal
        :show="historyOpen"
        :activities="activities"
        @close="historyOpen = false"
    />

    <!-- Modal de Destinatários do Relatório -->
    <ReportRecipientsModal
        :show="recipientsOpen"
        :intended-recipients="intendedRecipients"
        :recipients="recipients"
        @close="recipientsOpen = false"
        @revoke="
            (recipientId) =>
                router.delete(
                    revokeRecipient.url({
                        current_team: currentTeam.slug,
                        report: report.id,
                        recipient: recipientId,
                    }),
                )
        "
    />

    <!-- Agente Consolidado de IA Flutuante -->
    <FloatingConsolidatedReportAgent
        :open="agentOpen"
        :team-slug="currentTeam.slug"
        :report-id="report.id"
        :report-title="report.title"
        @open="agentOpen = true"
        @close="agentOpen = false"
        @suggestions="addAgentSuggestions"
        @focus="focusSystem"
    />

    <!-- Modal de Seleção de Sistema para Agrupamento -->
    <ReportGroupSystemModal
        :show="groupModal.open && Boolean(groupModal.sourceSystem)"
        :source-system="groupModal.sourceSystem"
        :available-targets="
            groupModal.sourceSystem
                ? availableTargetSystems(groupModal.sourceSystem)
                : []
        "
        v-model:target-system-id="groupModal.targetSystemId"
        @close="groupModal.open = false"
        @confirm="confirmGroupSelection"
    />
</template>
