<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    List,
    Loader2,
    Mail,
    PanelLeft,
    PanelLeftOpen,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    reactive,
    ref,
    watch,
} from 'vue';
import FloatingConsolidatedReportAgent from '@/features/reports/components/FloatingConsolidatedReportAgent.vue';
import type { ConsolidatedSuggestion } from '@/features/reports/components/FloatingConsolidatedReportAgent.vue';
import ParameterChartModal from '@/features/reports/components/ParameterChartModal.vue';
import ReportHistoryModal from '@/features/reports/components/ReportHistoryModal.vue';
import ReportRecipientsModal from '@/features/reports/components/ReportRecipientsModal.vue';
import type {
    DeliveryHistoryEntry,
    ReportRecipientItem,
} from '@/features/reports/components/ReportRecipientsModal.vue';
import ReportShowHeader from '@/features/reports/components/ReportShowHeader.vue';
import ReportShowToolbar from '@/features/reports/components/ReportShowToolbar.vue';
import ReportSystemSection from '@/features/reports/components/ReportSystemSection.vue';
import ReportSystemsSidebarNav from '@/features/reports/components/ReportSystemsSidebarNav.vue';
import { formatDateBr } from '@/features/reports/reportMetrics';
import type { ReportPhrase, ReportTableData } from '@/features/reports/types';
import {
    finalize,
    resend,
    show as showReport,
    store as storeReport,
} from '@/routes/reports';
import { update as updateSuggestion } from '@/routes/reports/suggestions';
import {
    chart as chartRoute,
    data as dataRoute,
    update as updateSystem,
} from '@/routes/reports/systems';
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
    issuerName?: string | null;
    dataCountsBySystem?: Record<number, number>;
    systems: SystemSection[];
    recipients: ReportRecipientItem[];
    deliveryHistory?: DeliveryHistoryEntry[];
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
    activeRecipientsCount?: number;
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

const sortedSections = computed(() =>
    [...topLevelSections.value].sort(
        (a, b) => a.sort_order - b.sort_order || a.id - b.id,
    ),
);

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

function availableGroupTargets(
    system: SystemSection,
): Array<{ id: number; label: string }> {
    return sortedSections.value
        .filter((s) => s.id !== system.id)
        .map((s) => ({
            id: s.id,
            label: [s, ...getGroupedChildren(s.id)]
                .map((m) => m.name)
                .join(' / '),
        }));
}

function canGroup(system: SystemSection): boolean {
    if (!system.item?.show_data_results) {
        return false;
    }

    return availableGroupTargets(system).length > 0;
}

const isGrouping = ref(false);
const editorReloadTriggers = reactive<Record<number, number>>({});

async function handleGroupTarget(
    sourceSystem: SystemSection,
    targetSystemId: number,
): Promise<void> {
    if (isGrouping.value) {
        return;
    }

    const targetSystem = sections.value.find((s) => s.id === targetSystemId);

    if (!targetSystem) {
        return;
    }

    if (
        (dirtySystems[sourceSystem.id] || dirtySystems[targetSystemId]) &&
        !window.confirm(
            'Existem alterações ainda não salvas no editor. Deseja descartá-las antes de agrupar?',
        )
    ) {
        return;
    }

    await groupSystem(targetSystem, sourceSystem.id, false);
}

async function handleUngroupMember(
    sourceSystem: SystemSection,
    member: SystemSection,
): Promise<void> {
    if (isGrouping.value) {
        return;
    }

    if (
        dirtySystems[sourceSystem.id] &&
        !window.confirm(
            'Existem alterações ainda não salvas no editor. Deseja descartá-las antes de desagrupar?',
        )
    ) {
        return;
    }

    await groupSystem(member, null, false, sourceSystem.id);
}

async function groupSystem(
    system: SystemSection,
    parentSystemId: number | null,
    clearComment: boolean,
    originalGroupId?: number,
): Promise<void> {
    const previousParentId = system.item?.parent_monitored_system_id ?? null;
    isGrouping.value = true;

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
            hide_data: result.hide_data ?? system.default_hide_data ?? false,
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

        const reloadTargets = new Set<number>([system.id]);

        if (parentSystemId !== null) {
            reloadTargets.add(parentSystemId);
            const parent = sections.value.find((s) => s.id === parentSystemId);

            if (parent) {
                await loadSystem(parent, true);
            }
        } else {
            await loadSystem(system, true);

            if (previousParentId !== null) {
                reloadTargets.add(previousParentId);
                const prevParent = sections.value.find(
                    (s) => s.id === previousParentId,
                );

                if (prevParent) {
                    await loadSystem(prevParent, true);
                }
            }

            if (originalGroupId !== undefined) {
                reloadTargets.add(originalGroupId);
                const origChildren = getGroupedChildren(originalGroupId);

                if (origChildren[0]) {
                    reloadTargets.add(origChildren[0].id);
                    await loadSystem(origChildren[0], true);
                }
            }
        }

        router.reload({
            only: ['systems', 'activities'],
            onSuccess: () => {
                for (const id of reloadTargets) {
                    editorReloadTriggers[id] =
                        (editorReloadTriggers[id] ?? 0) + 1;
                }
            },
        });
    } catch {
        // Handle error gracefully
    } finally {
        isGrouping.value = false;
    }
}

const defaultEndDate = props.report.date_reference;
const defaultStartDate = (() => {
    const end = new Date(`${props.report.date_reference}T12:00:00`);
    end.setDate(end.getDate() - 6);

    return end.toISOString().split('T')[0];
})();

const tableStartDate = ref(defaultStartDate);
const tableEndDate = ref(defaultEndDate);

function onTablePeriodChange(dates: { start_date: string; end_date: string }) {
    tableStartDate.value = dates.start_date;
    tableEndDate.value = dates.end_date;

    for (const section of sections.value) {
        if (systemData[section.id]) {
            void loadSystem(section, true, dates);
        }
    }
}

function getSystemDates() {
    return {
        start_date: tableStartDate.value,
        end_date: tableEndDate.value,
    };
}

const systemData = reactive<Record<number, ReportTableData>>({});
const loadingSystems = reactive<Record<number, boolean>>({});
const togglingHideData = reactive<Record<number, boolean>>({});
const editingSystems = reactive<Record<number, boolean>>({});
const dirtySystems = reactive<Record<number, boolean>>({});
const finalizeConfirmOpen = ref(false);
const isFinalizing = ref(false);
const isResending = ref(false);
const historyOpen = ref(false);
const recipientsOpen = ref(false);
const agentOpen = ref(false);
const highlightedSystemId = ref<number | null>(null);
const chart = ref<{ open: boolean; url: string; title: string }>({
    open: false,
    url: '',
    title: '',
});

const LAYOUT_MODE_KEY = 'consucal-report-layout-mode';
const NAV_OPEN_KEY = 'consucal-report-nav-open';

const layoutMode = ref<'sequence' | 'lateral'>('sequence');
const navOpen = ref(true);
const activeNavSystemId = ref<number | null>(null);
const lateralSystemId = ref<number | null>(null);
const displaySignal = ref<{
    seq: number;
    mode: 'editor' | 'both' | 'close';
} | null>(null);
const focusSignals = reactive<
    Record<number, { seq: number; openTable?: boolean }>
>({});
let signalCounter = 0;

const activeLateralIndex = computed(() => {
    const list = sortedSections.value;

    if (list.length === 0) {
        return -1;
    }

    const idx = list.findIndex((s) => s.id === lateralSystemId.value);

    return idx >= 0 ? idx : 0;
});

const activeLateralSystem = computed(() => {
    const list = sortedSections.value;

    if (list.length === 0 || activeLateralIndex.value < 0) {
        return null;
    }

    return list[activeLateralIndex.value];
});

function setLayoutMode(nextMode: 'sequence' | 'lateral'): void {
    if (nextMode === layoutMode.value) {
        return;
    }

    layoutMode.value = nextMode;

    try {
        sessionStorage.setItem(LAYOUT_MODE_KEY, nextMode);
    } catch {
        // Ignore storage errors
    }

    if (nextMode === 'lateral') {
        const candidateId =
            activeNavSystemId.value &&
            sortedSections.value.some((s) => s.id === activeNavSystemId.value)
                ? activeNavSystemId.value
                : (sortedSections.value[0]?.id ?? null);
        lateralSystemId.value = candidateId;
        activeNavSystemId.value = candidateId;
        navOpen.value = !window.matchMedia('(max-width: 1023px)').matches;
    }
}

function setNavOpen(open: boolean): void {
    navOpen.value = open;

    if (layoutMode.value === 'sequence') {
        try {
            sessionStorage.setItem(NAV_OPEN_KEY, String(open));
        } catch {
            // Ignore storage errors
        }
    }
}

function canLeaveCurrentLateralEditor(): boolean {
    const current = activeLateralSystem.value;

    if (!current || !editingSystems[current.id]) {
        return true;
    }

    return window.confirm(
        `Existem alterações em edição em "${current.name}". Deseja sair desta edição e trocar de sistema?`,
    );
}

function selectLateralSystem(systemId: number): void {
    if (systemId === activeLateralSystem.value?.id) {
        if (window.matchMedia('(max-width: 1023px)').matches) {
            navOpen.value = false;
        }

        return;
    }

    if (!canLeaveCurrentLateralEditor()) {
        return;
    }

    if (activeLateralSystem.value) {
        editingSystems[activeLateralSystem.value.id] = false;
    }

    lateralSystemId.value = systemId;
    activeNavSystemId.value = systemId;

    if (window.matchMedia('(max-width: 1023px)').matches) {
        navOpen.value = false;
    }
}

function navigateLateralStep(delta: -1 | 1): void {
    const list = sortedSections.value;
    const nextIndex = activeLateralIndex.value + delta;

    if (nextIndex < 0 || nextIndex >= list.length) {
        return;
    }

    selectLateralSystem(list[nextIndex].id);
}

function handleDisplayAll(mode: 'editor' | 'both' | 'close'): void {
    if (mode === 'close') {
        const editingNames = sortedSections.value
            .filter((s) => editingSystems[s.id])
            .map((s) => s.name);

        if (editingNames.length > 0) {
            window.alert(
                `Salve ou cancele a edição antes de recolher: ${editingNames.join(', ')}.`,
            );

            return;
        }
    }

    displaySignal.value = { seq: ++signalCounter, mode };
}

function handleNavSelect(systemId: number): void {
    if (layoutMode.value === 'lateral') {
        selectLateralSystem(systemId);

        return;
    }

    void focusSystem(systemId, true);

    if (window.matchMedia('(max-width: 1023px)').matches) {
        navOpen.value = false;
    }
}

let scrollSyncFrame = 0;

function syncActiveNavFromScroll(): void {
    scrollSyncFrame = 0;

    if (layoutMode.value !== 'sequence') {
        return;
    }

    const cards = Array.from(
        document.querySelectorAll<HTMLElement>('[data-report-system-id]'),
    );

    if (cards.length === 0) {
        return;
    }

    const readingLine = Math.min(200, Math.max(120, window.innerHeight * 0.22));
    let candidate = cards[0];

    for (const card of cards) {
        if (card.getBoundingClientRect().top <= readingLine) {
            candidate = card;
        } else {
            break;
        }
    }

    const id = Number(candidate.dataset.reportSystemId);

    if (!Number.isNaN(id)) {
        activeNavSystemId.value = id;
    }
}

function queueScrollSync(): void {
    if (scrollSyncFrame) {
        return;
    }

    scrollSyncFrame = window.requestAnimationFrame(syncActiveNavFromScroll);
}

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

async function loadSystem(
    system: SystemSection,
    force = false,
    datesOverride?: { start_date: string; end_date: string },
) {
    if ((systemData[system.id] && !force) || loadingSystems[system.id]) {
        return;
    }

    loadingSystems[system.id] = true;
    const dates = datesOverride ?? getSystemDates();

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
        hide_data: result.hide_data ?? system.default_hide_data ?? false,
        is_stopped: false,
        comment: null,
        updated_at: result.updated_at,
        comments: [],
        suggestions: [],
    };
    system.item.show_data_results = result.show_data_results;
    system.item.hide_data = result.hide_data ?? system.item.hide_data;

    if (result.show_data_results) {
        await loadSystem(system);
    }
}

async function toggleHideData(system: SystemSection) {
    if (togglingHideData[system.id]) {
        return;
    }

    togglingHideData[system.id] = true;

    try {
        const currentHide =
            system.item?.hide_data ?? system.default_hide_data ?? false;
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
                    hide_data: !currentHide,
                }),
            },
        );

        if (!response.ok) {
            return;
        }

        const result = await response.json();

        system.item ??= {
            id: result.item_id,
            show_data_results: result.show_data_results ?? false,
            hide_data: false,
            is_stopped: false,
            comment: null,
            updated_at: result.updated_at,
            comments: [],
            suggestions: [],
        };
        system.item.hide_data = result.hide_data;
    } finally {
        togglingHideData[system.id] = false;
    }
}

const resolvedActiveRecipientsCount = computed(
    () =>
        props.activeRecipientsCount ??
        props.recipients.filter((r) => !r.revoked_at).length,
);

const hasUnsavedEditorChanges = computed(() =>
    Object.values(dirtySystems).some(Boolean),
);

function finish() {
    if (isFinalizing.value) {
        return;
    }

    finalizeConfirmOpen.value = true;
}

function confirmFinalize() {
    if (isFinalizing.value) {
        return;
    }

    router.post(
        finalize.url({
            current_team: props.currentTeam.slug,
            report: props.report.id,
        }),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isFinalizing.value = true;
            },
            onSuccess: () => {
                finalizeConfirmOpen.value = false;
            },
            onFinish: () => {
                isFinalizing.value = false;
            },
        },
    );
}

function sendAgain() {
    if (isResending.value) {
        return;
    }

    router.post(
        resend.url({
            current_team: props.currentTeam.slug,
            report: props.report.id,
        }),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isResending.value = true;
            },
            onFinish: () => {
                isResending.value = false;
            },
        },
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

async function focusSystem(systemId: number, openSection = false) {
    if (layoutMode.value === 'lateral') {
        selectLateralSystem(systemId);
    }

    if (openSection) {
        focusSignals[systemId] = { seq: ++signalCounter };
    }

    activeNavSystemId.value = systemId;
    highlightedSystemId.value = systemId;
    await nextTick();
    document
        .querySelector(`[data-report-system-id="${systemId}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

function handleEditorSaved(
    system: SystemSection,
    savedItem?: ReportPhrase | null,
): void {
    if (savedItem) {
        if (system.item) {
            system.item.comment = savedItem.html;
            system.item.document = savedItem.document;
            system.item.charts = savedItem.charts;
            system.item.show_data_results = savedItem.show_data_results;
            system.item.hide_data = savedItem.hide_data;
            system.item.is_stopped = savedItem.is_stopped;
            system.item.updated_at = savedItem.updated_at;
        } else {
            system.item = {
                id: savedItem.id,
                parent_report_item_id: savedItem.parent_report_item_id,
                parent_monitored_system_id: null,
                show_data_results: savedItem.show_data_results,
                hide_data: savedItem.hide_data,
                is_stopped: savedItem.is_stopped,
                comment: savedItem.html,
                document: savedItem.document,
                charts: savedItem.charts,
                updated_at: savedItem.updated_at,
                comments: [],
                suggestions: [],
            };
        }
    }

    router.reload();
}

function handleSystemEditingChange(systemId: number, editing: boolean): void {
    editingSystems[systemId] = editing;
}

function handleSystemDirtyChange(systemId: number, dirty: boolean): void {
    dirtySystems[systemId] = dirty;
}

onMounted(() => {
    const isNarrow = window.matchMedia('(max-width: 1023px)').matches;

    try {
        const storedMode = sessionStorage.getItem(LAYOUT_MODE_KEY);

        if (storedMode === 'lateral' || storedMode === 'sequence') {
            layoutMode.value = storedMode;
        }

        const storedNav = sessionStorage.getItem(NAV_OPEN_KEY);

        if (storedNav !== null && layoutMode.value === 'sequence') {
            navOpen.value = storedNav === 'true' && !isNarrow;
        } else {
            navOpen.value = !isNarrow;
        }
    } catch {
        navOpen.value = !isNarrow;
    }

    activeNavSystemId.value = sortedSections.value[0]?.id ?? null;
    lateralSystemId.value = sortedSections.value[0]?.id ?? null;

    window.addEventListener('scroll', queueScrollSync, { passive: true });
    window.addEventListener('resize', queueScrollSync, { passive: true });

    sections.value
        .filter((system) => system.item?.show_data_results)
        .forEach((system) => loadSystem(system));
});

onUnmounted(() => {
    window.removeEventListener('scroll', queueScrollSync);
    window.removeEventListener('resize', queueScrollSync);
});
</script>

<template>
    <div
        class="report-elaboration-theme min-h-full w-full min-w-0 flex-1 bg-background text-foreground"
    >
        <Head :title="report.title" />

        <div
            class="mx-auto grid w-full max-w-[1640px] min-w-0 gap-3 p-4 lg:p-5"
        >
            <!-- Cabeçalho Administrativo -->
            <ReportShowHeader
                :report="report"
                :current-team="currentTeam"
                :intended-recipients-count="intendedRecipients.length"
                :active-recipients-count="resolvedActiveRecipientsCount"
                :issuer-name="issuerName"
                :pending-date="pendingDate"
                :date-prompt="datePrompt"
                @date-change="onDateInputChange"
                @cancel-date-prompt="cancelDatePrompt"
                @confirm-date-prompt="confirmDatePrompt"
                @open-recipients="recipientsOpen = true"
            />

            <!-- Barra Sticky de Ferramentas de Elaboração (direta no container da página para acompanhar todo o scroll) -->
            <ReportShowToolbar
                :report="report"
                :current-team="currentTeam"
                :sections="sortedSections"
                :editing-systems="editingSystems"
                :activities-count="activities.length"
                :table-start-date="tableStartDate"
                :table-end-date="tableEndDate"
                :layout-mode="layoutMode"
                :is-finalizing="isFinalizing"
                :is-resending="isResending"
                @update:table-start-date="(val) => (tableStartDate = val)"
                @update:table-end-date="(val) => (tableEndDate = val)"
                @display-all="handleDisplayAll"
                @focus-system="(id) => focusSystem(id, true)"
                @table-period-change="onTablePeriodChange"
                @open-history="historyOpen = true"
                @send-again="sendAgain"
                @finish="finish"
            />

            <!-- Layout dos Blocos -->
            <section
                aria-label="Layout dos Blocos"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-3.5 py-2.5 text-card-foreground shadow-2xs"
            >
                <div class="space-y-0.5">
                    <h2 class="text-xs font-semibold text-foreground">
                        Layout dos Blocos
                    </h2>
                    <p class="text-[10.5px] text-muted-foreground">
                        Escolha como deseja navegar e editar os sistemas deste
                        relatório.
                    </p>
                </div>

                <div
                    role="tablist"
                    aria-label="Modo de layout dos blocos"
                    class="inline-flex rounded-lg border border-border bg-muted p-0.5"
                >
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="layoutMode === 'sequence'"
                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] font-medium transition"
                        :class="
                            layoutMode === 'sequence'
                                ? 'bg-card font-semibold text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="setLayoutMode('sequence')"
                    >
                        <List class="size-3.5" />
                        <span>Sistemas em sequência</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="layoutMode === 'lateral'"
                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] font-medium transition"
                        :class="
                            layoutMode === 'lateral'
                                ? 'bg-card font-semibold text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="setLayoutMode('lateral')"
                    >
                        <PanelLeft class="size-3.5" />
                        <span>Navegação lateral</span>
                    </button>
                </div>
            </section>

            <!-- Expandir navegação quando minimizada em sequência ou no modo lateral mobile -->
            <div
                v-if="!navOpen"
                class="flex items-center"
                :class="layoutMode === 'lateral' ? 'lg:hidden' : ''"
            >
                <button
                    v-if="layoutMode === 'sequence'"
                    type="button"
                    aria-label="Expandir navegação dos sistemas"
                    title="Expandir navegação dos sistemas"
                    class="inline-flex size-9 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground shadow-2xs transition hover:bg-muted hover:text-foreground"
                    @click="setNavOpen(true)"
                >
                    <PanelLeftOpen class="size-4" />
                </button>
                <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs font-semibold text-foreground shadow-2xs lg:hidden"
                    @click="setNavOpen(true)"
                >
                    <PanelLeft class="size-4 text-primary" />
                    <span
                        >Sistemas do relatório ({{
                            sortedSections.length
                        }})</span
                    >
                </button>
            </div>

            <!-- Área Principal: Painel Lateral de Navegação + Cartões de Avaliação -->
            <div
                class="grid min-w-0 items-start gap-4"
                :class="
                    navOpen || layoutMode === 'lateral'
                        ? 'lg:grid-cols-[276px_minmax(0,1fr)]'
                        : 'grid-cols-1'
                "
            >
                <ReportSystemsSidebarNav
                    :open="navOpen || layoutMode === 'lateral'"
                    :mode="layoutMode"
                    :sections="sortedSections"
                    :all-systems="sections"
                    :editing-systems="editingSystems"
                    :active-system-id="
                        layoutMode === 'lateral'
                            ? (activeLateralSystem?.id ?? null)
                            : activeNavSystemId
                    "
                    :data-counts-by-system="dataCountsBySystem"
                    @select="handleNavSelect"
                    @close="setNavOpen(false)"
                />

                <!-- Modo Navegação Lateral: um único cartão/editor completo à direita -->
                <div v-if="layoutMode === 'lateral'" class="min-w-0 space-y-3">
                    <div
                        v-if="activeLateralSystem"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-2xs"
                    >
                        <div class="min-w-0">
                            <span
                                class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Elaboração individual
                            </span>
                            <strong
                                class="block truncate text-sm font-bold text-foreground"
                            >
                                {{ getCombinedTitle(activeLateralSystem) }}
                            </strong>
                            <small class="text-[11px] text-muted-foreground">
                                {{
                                    getGroupedChildren(activeLateralSystem.id)
                                        .length > 0
                                        ? `${getGroupedChildren(activeLateralSystem.id).length + 1} sistemas agrupados · uma única avaliação`
                                        : 'Editor de considerações do sistema selecionado'
                                }}
                            </small>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="mr-1 text-xs font-semibold text-muted-foreground tabular-nums"
                            >
                                Avaliação {{ activeLateralIndex + 1 }} de
                                {{ sortedSections.length }}
                            </span>
                            <button
                                type="button"
                                :disabled="activeLateralIndex <= 0"
                                class="inline-flex h-8 items-center gap-1 rounded-lg border border-border bg-background px-2.5 text-xs font-medium text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-45"
                                @click="navigateLateralStep(-1)"
                            >
                                <ArrowLeft class="size-3.5" />
                                <span>Anterior</span>
                            </button>
                            <button
                                type="button"
                                :disabled="
                                    activeLateralIndex >=
                                    sortedSections.length - 1
                                "
                                class="inline-flex h-8 items-center gap-1 rounded-lg border border-border bg-background px-2.5 text-xs font-medium text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-45"
                                @click="navigateLateralStep(1)"
                            >
                                <span>Próximo</span>
                                <ArrowRight class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <ReportSystemSection
                        v-if="activeLateralSystem"
                        :key="`lateral-${activeLateralSystem.id}`"
                        :system="activeLateralSystem"
                        :grouped-children="
                            getGroupedChildren(activeLateralSystem.id)
                        "
                        :combined-title="getCombinedTitle(activeLateralSystem)"
                        :can-group="canGroup(activeLateralSystem)"
                        :available-group-targets="
                            availableGroupTargets(activeLateralSystem)
                        "
                        :is-grouping="isGrouping"
                        :editor-reload-trigger="
                            editorReloadTriggers[activeLateralSystem.id] ?? 0
                        "
                        :is-highlighted="
                            highlightedSystemId === activeLateralSystem.id
                        "
                        :system-dates="getSystemDates()"
                        :is-loading-data="
                            loadingSystems[activeLateralSystem.id] || false
                        "
                        :is-toggling-hide-data="
                            togglingHideData[activeLateralSystem.id] || false
                        "
                        :system-data="systemData[activeLateralSystem.id]"
                        :data-counts-by-system="dataCountsBySystem"
                        :current-team="currentTeam"
                        :systems="systems"
                        :report-date-reference="report.date_reference"
                        :resolving-suggestions="resolvingSuggestions"
                        :is-lateral-mode="true"
                        :focus-signal="
                            focusSignals[activeLateralSystem.id] ?? null
                        "
                        @group-target="handleGroupTarget"
                        @ungroup-member="
                            (member) =>
                                handleUngroupMember(
                                    activeLateralSystem!,
                                    member,
                                )
                        "
                        @toggle-hide-data="toggleHideData"
                        @toggle-system="toggleSystem"
                        @load-data="loadSystem"
                        @chart="openChart"
                        @resolve-suggestion="resolveSuggestion"
                        @editor-saved="
                            (savedItem) =>
                                handleEditorSaved(
                                    activeLateralSystem!,
                                    savedItem,
                                )
                        "
                        @editing-change="handleSystemEditingChange"
                        @dirty-change="handleSystemDirtyChange"
                    />
                </div>

                <!-- Modo Sistemas em Sequência: todos os cartões verticalmente -->
                <div v-else class="min-w-0 space-y-2.5">
                    <ReportSystemSection
                        v-for="system in sortedSections"
                        :key="system.id"
                        :system="system"
                        :grouped-children="getGroupedChildren(system.id)"
                        :combined-title="getCombinedTitle(system)"
                        :can-group="canGroup(system)"
                        :available-group-targets="availableGroupTargets(system)"
                        :is-grouping="isGrouping"
                        :editor-reload-trigger="
                            editorReloadTriggers[system.id] ?? 0
                        "
                        :is-highlighted="highlightedSystemId === system.id"
                        :system-dates="getSystemDates()"
                        :is-loading-data="loadingSystems[system.id] || false"
                        :is-toggling-hide-data="
                            togglingHideData[system.id] || false
                        "
                        :system-data="systemData[system.id]"
                        :data-counts-by-system="dataCountsBySystem"
                        :current-team="currentTeam"
                        :systems="systems"
                        :report-date-reference="report.date_reference"
                        :resolving-suggestions="resolvingSuggestions"
                        :display-signal="displaySignal"
                        :focus-signal="focusSignals[system.id] ?? null"
                        @group-target="handleGroupTarget"
                        @ungroup-member="
                            (member) => handleUngroupMember(system, member)
                        "
                        @toggle-hide-data="toggleHideData"
                        @toggle-system="toggleSystem"
                        @load-data="loadSystem"
                        @chart="openChart"
                        @resolve-suggestion="resolveSuggestion"
                        @editor-saved="
                            (savedItem) => handleEditorSaved(system, savedItem)
                        "
                        @editing-change="handleSystemEditingChange"
                        @dirty-change="handleSystemDirtyChange"
                    />
                </div>
            </div>
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
            :recipients="recipients"
            :delivery-history="deliveryHistory"
            :team-slug="currentTeam.slug"
            :report-id="report.id"
            :report-status="report.status"
            :report-date-reference="report.date_reference"
            @close="recipientsOpen = false"
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

        <!-- Modal de Confirmação para Finalizar Relatório -->
        <Dialog v-model:open="finalizeConfirmOpen">
            <DialogContent data-test="finalize-confirm-dialog">
                <DialogHeader>
                    <DialogTitle>Finalizar relatório</DialogTitle>
                    <DialogDescription>
                        Deseja finalizar o relatório de
                        <strong>{{
                            formatDateBr(report.date_reference)
                        }}</strong>
                        e enviar as notificações para
                        <strong>{{ resolvedActiveRecipientsCount }}</strong>
                        destinatário(s) ativo(s)?
                    </DialogDescription>
                </DialogHeader>

                <p
                    v-if="hasUnsavedEditorChanges"
                    class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                >
                    Existem alterações não salvas no editor que não serão
                    incluídas na versão finalizada.
                </p>

                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        data-test="cancel-finalize-report"
                        :disabled="isFinalizing"
                        @click="finalizeConfirmOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        data-test="confirm-finalize-report"
                        :disabled="isFinalizing"
                        class="bg-[#89653e] text-white hover:bg-[#75532f] dark:bg-[#a57a49] dark:text-[#141b20] dark:hover:bg-[#b88c58]"
                        @click="confirmFinalize"
                    >
                        <Loader2
                            v-if="isFinalizing"
                            class="size-4 animate-spin"
                        />
                        <Mail v-else class="size-4" />
                        <span>{{
                            isFinalizing
                                ? 'Finalizando...'
                                : 'Confirmar finalização'
                        }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
