<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRight,
    Loader2,
    MessageSquareText,
    Minus,
    Move,
    Save,
    X,
} from '@lucide/vue';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import Image from '@tiptap/extension-image';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import TextAlign from '@tiptap/extension-text-align';
import { FontFamily, FontSize, TextStyle } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import ChartBuilderModal from '@/features/reports/components/ChartBuilderModal.vue';
import FloatingReportAgent from '@/features/reports/components/FloatingReportAgent.vue';
import ReportChartUpdaterModal from '@/features/reports/components/ReportChartUpdaterModal.vue';
import ReportEditorToolbar from '@/features/reports/components/ReportEditorToolbar.vue';
import ReportHistoryPanel from '@/features/reports/components/ReportHistoryPanel.vue';
import ReportPhrasesPanel from '@/features/reports/components/ReportPhrasesPanel.vue';
import ReportSystemPickerModal from '@/features/reports/components/ReportSystemPickerModal.vue';
import { useFloatingDraggable } from '@/features/reports/composables/useFloatingDraggable';
import {
    ParameterReference,
    ReportChart,
    ReportIcon,
} from '@/features/reports/extensions';
import type {
    ReportChartConfig,
    ReportContext,
    ReportHistoryItem,
    ReportPhrase,
    ReportSystem,
} from '@/features/reports/types';
import { show, update } from '@/routes/reports/editor';
import {
    destroy as destroyGroup,
    store as storeGroup,
} from '@/routes/reports/groups';
import { index as historyRoute } from '@/routes/reports/history';
import type { Team } from '@/shared/types';

const props = defineProps<{
    currentTeam: Team;
    systems: ReportSystem[];
    selectedSystemIds: number[];
    defaultDate: string;
    buttonLabel?: string;
}>();

const emit = defineEmits<{
    systemChange: [systemId: number];
    saved: [];
    dateChange: [date: string];
}>();

const isOpen = ref(false);
const isChoosingSystem = ref(false);
const chosenSystemId = ref<number | null>(null);
const reportDate = ref(props.defaultDate);

watch(
    reportDate,
    (newDate) => {
        if (newDate) {
            emit('dateChange', newDate);
        }
    },
    { immediate: true },
);

watch(
    () => props.defaultDate,
    (newDate) => {
        if (newDate && newDate !== reportDate.value) {
            reportDate.value = newDate;
        }
    },
);

const context = ref<ReportContext | null>(null);
const isLoading = ref(false);
const isSaving = ref(false);
const isDirty = ref(false);
const hideData = ref(false);
const isStopped = ref(false);
const activePanel = ref<'history' | 'phrases' | null>(null);
const history = ref<ReportHistoryItem[]>([]);
const historyDate = ref('');
const isHistoryLoading = ref(false);
const showLatestChoice = ref(false);
const selectedPhraseIds = ref<number[]>([]);
const sourcePhraseId = ref<number | null>(null);
const showAgent = ref(false);
const editorShell = ref<HTMLElement | null>(null);
const isApplyingContent = ref(false);
const showChartDialog = ref(false);
const chartBeingEdited = ref<ReportChartConfig | null>(null);
const chartBeingEditedPosition = ref<number | null>(null);
const showChartUpdater = ref(false);
const selectedInlineParameterIds = ref<number[]>([]);
const bulkChartIds = ref<string[]>([]);

const { isFloating, isMinimized, position, toggleFloating, startDrag } =
    useFloatingDraggable();

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const editor = useEditor({
    extensions: [
        StarterKit,
        TextStyle,
        Color,
        FontFamily,
        FontSize,
        Highlight.configure({ multicolor: true }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Subscript,
        Superscript,
        Image.configure({ allowBase64: true }),
        ReportIcon,
        ParameterReference,
        ReportChart,
    ],
    content: '<p></p>',
    editorProps: {
        attributes: {
            class: 'min-h-72 max-w-none px-4 py-3 text-sm focus:outline-none',
            'data-test': 'report-editor-content',
        },
    },
    onUpdate: () => {
        if (!isApplyingContent.value) {
            isDirty.value = true;
        }
    },
});

const stateLabel = computed(() => {
    if (!context.value) {
        return '';
    }

    if (context.value.state === 'new_report') {
        return 'Novo relatório';
    }

    if (context.value.state === 'new_item') {
        return 'Novo comentário no relatório existente';
    }

    return `Relatório ${context.value.report?.status === 'completed' ? 'concluído (editável)' : 'em rascunho'}`;
});

const availableSystems = computed(() =>
    props.selectedSystemIds.length
        ? props.systems.filter((system) =>
              props.selectedSystemIds.includes(system.id),
          )
        : props.systems,
);

const currentSystemIndex = computed(() =>
    context.value
        ? context.value.systems.findIndex(
              (system) => system.id === context.value?.system.id,
          )
        : -1,
);

const chartNodes = computed(() => {
    const nodes: Array<Record<string, any>> = [];
    editor.value?.state.doc.descendants((node) => {
        if (node.type.name === 'reportChart') {
            nodes.push(node.attrs);
        }
    });

    return nodes;
});

function openLauncher() {
    if (props.selectedSystemIds.length === 1) {
        chosenSystemId.value = props.selectedSystemIds[0];
        openEditor();

        return;
    }

    chosenSystemId.value = availableSystems.value[0]?.id ?? null;
    isChoosingSystem.value = true;
}

async function openEditor() {
    if (!chosenSystemId.value) {
        return;
    }

    isChoosingSystem.value = false;
    isOpen.value = true;
    await loadContext();
}

async function loadContext(force = false) {
    if (!chosenSystemId.value) {
        return;
    }

    if (
        !force &&
        isDirty.value &&
        !window.confirm('Descartar alterações não salvas?')
    ) {
        return;
    }

    isLoading.value = true;

    try {
        const response = await fetch(
            show.url(
                { current_team: props.currentTeam.slug },
                {
                    query: {
                        date_reference: reportDate.value,
                        system_id: chosenSystemId.value,
                    },
                },
            ),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('Não foi possível abrir o relatório.');
        }

        context.value = await response.json();
        chosenSystemId.value = context.value!.system.id;
        hideData.value = context.value!.item?.hide_data ?? false;
        isStopped.value = context.value!.item?.is_stopped ?? false;
        applyContent(
            context.value!.item?.document,
            context.value!.item?.html ?? '<p></p>',
        );
        history.value = [];
        selectedPhraseIds.value = [];
        sourcePhraseId.value = null;
        isDirty.value = false;
    } finally {
        isLoading.value = false;
    }
}

function applyContent(
    document: Record<string, unknown> | null | undefined,
    html: string,
) {
    isApplyingContent.value = true;
    editor.value?.commands.setContent(document ?? html ?? '<p></p>');
    nextTick(() => {
        isApplyingContent.value = false;
        isDirty.value = false;
    });
}

async function save(force = false) {
    if (!context.value || !editor.value) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await fetch(
            update.url({ current_team: props.currentTeam.slug }),
            {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    date_reference: reportDate.value,
                    system_id: chosenSystemId.value,
                    document: editor.value.getJSON(),
                    html: editor.value.getHTML(),
                    hide_data: hideData.value,
                    show_data_results:
                        context.value.item?.show_data_results ?? false,
                    is_stopped: isStopped.value,
                    expected_updated_at: context.value.item?.updated_at ?? null,
                    force,
                }),
            },
        );

        if (response.status === 409) {
            if (
                window.confirm(
                    'Outro usuário alterou este item. Clique OK para sobrescrever ou Cancelar para carregar a versão do servidor.',
                )
            ) {
                await save(true);
            } else {
                await loadContext(true);
            }

            return;
        }

        if (!response.ok) {
            throw new Error('Falha ao salvar o relatório.');
        }

        context.value = await response.json();
        isDirty.value = false;
        isOpen.value = false;
        emit('saved');
    } finally {
        isSaving.value = false;
    }
}

async function navigateSystem(direction: -1 | 1) {
    if (!context.value) {
        return;
    }

    const systems = context.value.systems;
    const target =
        systems[
            (currentSystemIndex.value + direction + systems.length) %
                systems.length
        ];

    if (!target) {
        return;
    }

    chosenSystemId.value = target.id;
    await loadContext();

    if (context.value?.system.id === target.id) {
        emit('systemChange', target.id);
    } else {
        chosenSystemId.value = context.value?.system.id ?? null;
    }
}

function insertCheckPhrase() {
    editor.value
        ?.chain()
        .focus()
        .insertContent(
            'Todos os parâmetros estão dentro dos limites estabelecidos.',
        )
        .run();
}

function insertIcon(kind: 'error' | 'warning' | 'success' | 'info') {
    editor.value
        ?.chain()
        .focus()
        .insertContent([
            { type: 'reportIcon', attrs: { kind } },
            { type: 'text', text: ' ' },
        ])
        .run();
}

function naturalJoin(names: string[]) {
    if (names.length <= 1) {
        return names[0] ?? '';
    }

    return `${names.slice(0, -1).join(', ')} e ${names.at(-1)}`;
}

function insertParameters() {
    if (!context.value || selectedInlineParameterIds.value.length === 0) {
        return;
    }

    const selected = context.value.parameters.filter((parameter) =>
        selectedInlineParameterIds.value.includes(parameter.id),
    );
    editor.value
        ?.chain()
        .focus()
        .insertContent({
            type: 'parameterReference',
            attrs: {
                ids: selected.map((parameter) => parameter.id),
                label: naturalJoin(selected.map((parameter) => parameter.name)),
            },
        })
        .run();
    selectedInlineParameterIds.value = [];
}

async function loadHistory() {
    if (!context.value) {
        return;
    }

    activePanel.value = 'history';
    isHistoryLoading.value = true;

    try {
        const response = await fetch(
            historyRoute.url(
                { current_team: props.currentTeam.slug },
                {
                    query: {
                        system_id: chosenSystemId.value!,
                        before_date: reportDate.value,
                        date: historyDate.value || undefined,
                    },
                },
            ),
            { headers: { Accept: 'application/json' } },
        );
        history.value = response.ok ? (await response.json()).items : [];
    } finally {
        isHistoryLoading.value = false;
    }
}

function useHistorical(item: ReportHistoryItem, mode: 'append' | 'replace') {
    if (!editor.value) {
        return;
    }

    if (mode === 'replace') {
        applyContent(item.document, item.html);
        isDirty.value = true;

        return;
    }

    editor.value
        .chain()
        .focus()
        .insertContent(item.document?.content ?? item.html)
        .run();
}

function useLatest() {
    const latest = context.value?.latest_comment;

    if (!latest) {
        return;
    }

    const isEmpty = editor.value?.isEmpty ?? true;

    if (isEmpty) {
        useHistorical(latest, 'replace');
    } else {
        showLatestChoice.value = true;
    }
}

function applyLatest(mode: 'append' | 'replace') {
    const latest = context.value?.latest_comment;

    if (latest) {
        useHistorical(latest, mode);
    }

    showLatestChoice.value = false;
}

async function groupPhrases() {
    if (
        !context.value?.report ||
        selectedPhraseIds.value.length < 2 ||
        !sourcePhraseId.value
    ) {
        return;
    }

    const response = await fetch(
        storeGroup.url({ current_team: props.currentTeam.slug }),
        {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                report_id: context.value.report.id,
                item_ids: selectedPhraseIds.value,
                source_item_id: sourcePhraseId.value,
            }),
        },
    );

    if (response.ok) {
        context.value.phrases = (await response.json()).phrases;
        selectedPhraseIds.value = [];
        sourcePhraseId.value = null;
    }
}

async function ungroupPhrase(item: ReportPhrase) {
    const response = await fetch(
        destroyGroup.url({ current_team: props.currentTeam.slug }),
        {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ report_item_id: item.id }),
        },
    );

    if (response.ok && context.value) {
        context.value.phrases = (await response.json()).phrases;
    }
}

function insertAgentText(document: Record<string, unknown>, replace = false) {
    if (replace) {
        editor.value?.commands.setContent(document);
    } else {
        const content = Array.isArray(document.content)
            ? document.content
            : document;
        editor.value?.chain().focus().insertContent(content).run();
    }
}

function prepareChartDialog() {
    chartBeingEdited.value = null;
    chartBeingEditedPosition.value = null;
    showChartDialog.value = true;
}

function prepareChartEdit(event: Event) {
    const detail = (
        event as CustomEvent<{
            position: number;
            chart: ReportChartConfig;
        }>
    ).detail;

    chartBeingEdited.value = detail.chart;
    chartBeingEditedPosition.value = detail.position;
    showChartDialog.value = true;
}

function insertChart(chart: ReportChartConfig) {
    if (chartBeingEditedPosition.value !== null) {
        editor.value?.commands.command(({ tr, state }) => {
            const node = state.doc.nodeAt(chartBeingEditedPosition.value!);

            if (node?.type.name !== 'reportChart') {
                return false;
            }

            tr.setNodeMarkup(chartBeingEditedPosition.value!, undefined, chart);

            return true;
        });
        showChartDialog.value = false;
        chartBeingEdited.value = null;
        chartBeingEditedPosition.value = null;

        return;
    }

    editor.value
        ?.chain()
        .focus()
        .insertContent({
            type: 'reportChart',
            attrs: chart,
        })
        .run();
    showChartDialog.value = false;
}

function updateChartPeriods(days?: number, shift = false) {
    editor.value?.commands.command(({ tr, state }) => {
        state.doc.descendants((node, pos) => {
            if (
                node.type.name !== 'reportChart' ||
                !bulkChartIds.value.includes(node.attrs.id)
            ) {
                return;
            }

            let startDate = node.attrs.startDate;
            let endDate = node.attrs.endDate;

            if (days) {
                endDate = reportDate.value;
                const start = new Date(`${endDate}T12:00:00`);
                start.setDate(start.getDate() - (days - 1));
                startDate = start.toISOString().slice(0, 10);
            } else if (shift) {
                const start = new Date(`${startDate}T12:00:00`);
                const end = new Date(`${endDate}T12:00:00`);
                start.setDate(start.getDate() + 1);
                end.setDate(end.getDate() + 1);
                startDate = start.toISOString().slice(0, 10);
                endDate = end.toISOString().slice(0, 10);
            }

            tr.setNodeMarkup(pos, undefined, {
                ...node.attrs,
                startDate,
                endDate,
            });
        });

        return true;
    });
}

function beforeUnload(event: BeforeUnloadEvent) {
    if (!isDirty.value) {
        return;
    }

    event.preventDefault();
}

watch(
    () => props.defaultDate,
    (date) => {
        if (!isOpen.value) {
            reportDate.value = date;
        }
    },
);

onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
});
</script>

<template>
    <div data-report-launcher>
        <button
            v-if="!isOpen"
            type="button"
            data-test="create-report"
            class="inline-flex h-9 items-center gap-2 rounded-md bg-blue-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-blue-700"
            @click="openLauncher"
        >
            <MessageSquareText class="size-4" />
            {{ props.buttonLabel ?? 'Criar relatório' }}
        </button>

        <!-- Modal de Seleção de Sistema -->
        <ReportSystemPickerModal
            :show="isChoosingSystem"
            :available-systems="availableSystems"
            v-model:chosen-system-id="chosenSystemId"
            @close="isChoosingSystem = false"
            @confirm="openEditor"
        />

        <!-- Editor Modal/Floating Section -->
        <section
            v-if="isOpen"
            ref="editorShell"
            data-test="report-editor"
            class="mt-3 overflow-hidden rounded-xl border bg-card shadow-xl"
            :class="{
                'fixed z-[80] resize overflow-auto': isFloating,
                'max-h-12': isMinimized,
            }"
            :style="
                isFloating
                    ? {
                          left: `${position.x}px`,
                          top: `${position.y}px`,
                          width: `${position.width}px`,
                          height: `${position.height}px`,
                          maxWidth: 'calc(100vw - 16px)',
                          maxHeight: 'calc(100vh - 16px)',
                      }
                    : undefined
            "
        >
            <header
                class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/40 px-3 py-2"
                :class="{ 'cursor-move select-none': isFloating }"
                @pointerdown="startDrag"
            >
                <div class="flex items-center gap-2 text-xs text-foreground">
                    <strong>Sistema: {{ context?.system.name }}</strong>
                    <input
                        v-model="reportDate"
                        type="date"
                        class="h-8 rounded border bg-background px-2 text-foreground"
                        @change="loadContext()"
                    />
                    <button
                        data-test="save-report-top"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="isSaving"
                        @pointerdown.stop
                        @click.stop="save()"
                    >
                        <Loader2
                            v-if="isSaving"
                            class="size-3.5 animate-spin"
                        />
                        <Save v-else class="size-3.5" />
                        Salvar
                    </button>
                    <span
                        class="rounded bg-red-600 px-2 py-1 font-bold text-white"
                    >
                        {{ stateLabel }}
                    </span>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="editor-action"
                        title="Anterior"
                        @click.stop="navigateSystem(-1)"
                    >
                        <ArrowLeft class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="editor-action"
                        title="Próximo"
                        @click.stop="navigateSystem(1)"
                    >
                        <ArrowRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="editor-action"
                        title="Fixar/flutuar"
                        @click.stop="toggleFloating"
                    >
                        <Move class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="editor-action"
                        title="Minimizar"
                        @click.stop="isMinimized = !isMinimized"
                    >
                        <Minus class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="editor-action"
                        title="Fechar"
                        @click.stop="isOpen = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </header>

            <div v-if="!isMinimized">
                <!-- TipTap Toolbar -->
                <ReportEditorToolbar
                    :editor="editor"
                    :context="context"
                    v-model:hide-data="hideData"
                    v-model:is-stopped="isStopped"
                    v-model:selected-inline-parameter-ids="
                        selectedInlineParameterIds
                    "
                    v-model:show-latest-choice="showLatestChoice"
                    @insert-check-phrase="insertCheckPhrase"
                    @insert-icon="insertIcon"
                    @insert-parameters="insertParameters"
                    @use-latest="useLatest"
                    @apply-latest="applyLatest"
                    @load-history="loadHistory"
                    @toggle-phrases="
                        activePanel =
                            activePanel === 'phrases' ? null : 'phrases'
                    "
                    @open-agent="showAgent = true"
                    @prepare-chart-dialog="prepareChartDialog"
                    @open-chart-updater="
                        showChartUpdater = true;
                        bulkChartIds = chartNodes.map((node) => node.id);
                    "
                />

                <div
                    v-if="isLoading"
                    class="flex h-72 items-center justify-center"
                >
                    <Loader2 class="size-6 animate-spin text-blue-600" />
                </div>
                <EditorContent
                    v-else
                    :editor="editor"
                    class="report-editor border-b"
                    @report-chart-edit="prepareChartEdit"
                />

                <!-- Painel de Histórico Anterior -->
                <ReportHistoryPanel
                    v-if="activePanel === 'history'"
                    v-model:history-date="historyDate"
                    :history="history"
                    :is-loading="isHistoryLoading"
                    @close="activePanel = null"
                    @filter="loadHistory"
                    @use-historical="useHistorical"
                />

                <!-- Painel de Frases e Agrupamento -->
                <ReportPhrasesPanel
                    v-else-if="activePanel === 'phrases'"
                    v-model:selected-phrase-ids="selectedPhraseIds"
                    v-model:source-phrase-id="sourcePhraseId"
                    :phrases="context?.phrases"
                    @close="activePanel = null"
                    @group="groupPhrases"
                    @ungroup="ungroupPhrase"
                />

                <footer class="flex items-center justify-between gap-2 p-3">
                    <span class="text-xs text-muted-foreground">
                        {{
                            isDirty ? 'Alterações não salvas' : 'Conteúdo salvo'
                        }}
                    </span>
                    <button
                        data-test="save-report"
                        class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="isSaving"
                        @click="save()"
                    >
                        <Loader2 v-if="isSaving" class="size-4 animate-spin" />
                        <Save v-else class="size-4" />
                        Salvar
                    </button>
                </footer>
            </div>
        </section>

        <!-- Modal de Construção de Gráficos -->
        <ChartBuilderModal
            :open="showChartDialog"
            :team-slug="currentTeam.slug"
            :report-date="reportDate"
            :initial-chart="chartBeingEdited"
            @close="
                showChartDialog = false;
                chartBeingEdited = null;
                chartBeingEditedPosition = null;
            "
            @insert="insertChart"
        />

        <!-- Agente IA Flutuante -->
        <FloatingReportAgent
            :open="showAgent"
            :team-slug="currentTeam.slug"
            :date="reportDate"
            :system-id="chosenSystemId"
            :system-name="context?.system.name"
            :current-text="editor?.getText() ?? ''"
            @close="showAgent = false"
            @insert="insertAgentText"
        />

        <!-- Modal de Atualização em Lote de Gráficos -->
        <ReportChartUpdaterModal
            :show="showChartUpdater"
            :chart-nodes="chartNodes"
            v-model:bulk-chart-ids="bulkChartIds"
            @close="showChartUpdater = false"
            @update-periods="updateChartPeriods"
        />
    </div>
</template>

<style scoped>
.editor-action {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 0.25rem;
    background: var(--background);
}
.editor-action:hover {
    background: var(--muted);
}
.report-editor :deep(.ProseMirror) {
    min-height: 18rem;
}
.report-editor :deep(.report-icon-error) {
    color: #dc2626;
}
.report-editor :deep(.report-icon-warning) {
    color: #d97706;
}
.report-editor :deep(.report-icon-success) {
    color: #059669;
}
.report-editor :deep(.report-icon-info) {
    color: #2563eb;
}
</style>
