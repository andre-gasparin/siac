<script setup lang="ts">
import {
    ArrowUpRight,
    ChevronDown,
    ChevronsDown,
    ChevronsUp,
    ListChecks,
    MessageSquare,
    PanelTopClose,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Table2,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ClientReportSummaryPanel from '@/features/reports/components/ClientReportSummaryPanel.vue';
import type { SummarySystemRow } from '@/features/reports/components/ClientReportSummaryPanel.vue';
import {
    formatDateTimeBr,
    formatPercentBr,
    PARAMETER_CATEGORY_CODES,
    PARAMETER_CATEGORY_META,
} from '@/features/reports/reportMetrics';
import type { ParameterCategoryCode } from '@/features/reports/types';

export interface ViewPreferences {
    tableFont: number;
    technicalFont: number;
    categories: Record<ParameterCategoryCode, boolean>;
    showTags: boolean;
    showMean: boolean;
    showCharts: boolean;
}

export interface CentralCommentEntry {
    id: number;
    systemId: number;
    systemName: string;
    author: string;
    isConsucal: boolean;
    body: string;
    createdAt: string;
}

const props = defineProps<{
    dateReference: string;
    preferences: ViewPreferences;
    summaryRows: SummarySystemRow[];
    summaryTotals: {
        total: number;
        eligible: number;
        deviations: number;
    };
    centralComments: CentralCommentEntry[];
}>();

const emit = defineEmits<{
    'update:preferences': [prefs: ViewPreferences];
    setViewMode: [mode: 'all' | 'none' | 'compact'];
    resetPreferences: [];
    navigateSystem: [systemId: number];
}>();

const toolsCollapsed = ref(false);
const viewMenuOpen = ref(false);
const navigateMenuOpen = ref(false);
const summaryOpen = ref(false);
const centralOpen = ref(false);
const systemSearch = ref('');

const viewMenuRef = ref<HTMLElement | null>(null);
const navigateMenuRef = ref<HTMLElement | null>(null);

const overallConformity = computed(() =>
    formatPercentBr(
        props.summaryTotals.eligible - props.summaryTotals.deviations,
        props.summaryTotals.eligible,
    ),
);

const filteredNavSystems = computed(() => {
    const q = systemSearch.value
        .trim()
        .toLocaleLowerCase('pt-BR')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    if (!q) {
        return props.summaryRows;
    }

    return props.summaryRows.filter((row) =>
        row.name
            .toLocaleLowerCase('pt-BR')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .includes(q),
    );
});

const blocksWithCommentsCount = computed(
    () => new Set(props.centralComments.map((c) => c.systemId)).size,
);

const latestCommentDate = computed(() => {
    if (props.centralComments.length === 0) {
        return null;
    }

    const sorted = [...props.centralComments].sort((a, b) =>
        b.createdAt.localeCompare(a.createdAt),
    );

    return formatDateTimeBr(sorted[0].createdAt);
});

const orderedCentralComments = computed(() =>
    [...props.centralComments].sort((a, b) =>
        b.createdAt.localeCompare(a.createdAt),
    ),
);

function updatePref<K extends keyof ViewPreferences>(
    key: K,
    value: ViewPreferences[K],
) {
    emit('update:preferences', {
        ...props.preferences,
        [key]: value,
    });
}

function toggleCategory(code: ParameterCategoryCode) {
    emit('update:preferences', {
        ...props.preferences,
        categories: {
            ...props.preferences.categories,
            [code]: !props.preferences.categories[code],
        },
    });
}

function handleNavigateFromMenu(systemId: number) {
    navigateMenuOpen.value = false;
    summaryOpen.value = false;
    centralOpen.value = false;
    emit('navigateSystem', systemId);
}

function handleClickOutside(event: MouseEvent) {
    const target = event.target as Node;

    if (
        viewMenuOpen.value &&
        viewMenuRef.value &&
        !viewMenuRef.value.contains(target)
    ) {
        viewMenuOpen.value = false;
    }

    if (
        navigateMenuOpen.value &&
        navigateMenuRef.value &&
        !navigateMenuRef.value.contains(target)
    ) {
        navigateMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <nav
        aria-label="Ferramentas do relatório"
        class="sticky top-0 z-30 rounded-xl border border-border bg-card/95 text-card-foreground shadow-sm backdrop-blur-sm"
    >
        <!-- Faixa superior de recolhimento -->
        <button
            type="button"
            :aria-expanded="!toolsCollapsed"
            class="flex w-full items-center justify-between gap-2 px-3.5 py-1.5 text-left text-[10.5px] font-semibold text-muted-foreground transition hover:bg-muted/40"
            :class="
                toolsCollapsed
                    ? 'rounded-xl'
                    : 'rounded-t-xl border-b border-border/60'
            "
            @click="toolsCollapsed = !toolsCollapsed"
        >
            <span class="inline-flex items-center gap-2">
                <SlidersHorizontal class="size-3.5" />
                <span>Ferramentas do relatório</span>
            </span>
            <span class="inline-flex items-center gap-1 font-semibold">
                <span>{{ toolsCollapsed ? 'Expandir' : 'Recolher' }}</span>
                <ChevronDown
                    class="size-3.5 transition-transform duration-200"
                    :class="toolsCollapsed ? '' : 'rotate-180'"
                />
            </span>
        </button>

        <div v-show="!toolsCollapsed">
            <!-- Linha principal de ferramentas -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Opções de Visualização -->
                    <div ref="viewMenuRef" class="relative">
                        <button
                            type="button"
                            :aria-expanded="viewMenuOpen"
                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-[11.5px] font-medium text-foreground transition hover:bg-muted"
                            @click="viewMenuOpen = !viewMenuOpen"
                        >
                            <Table2 class="size-3.5 text-muted-foreground" />
                            <span>Opções de Visualização</span>
                            <ChevronDown
                                class="size-3.5 text-muted-foreground"
                            />
                        </button>

                        <div
                            v-if="viewMenuOpen"
                            class="absolute top-full left-0 z-50 mt-2 max-h-[75vh] w-[min(560px,calc(100vw-32px))] overflow-y-auto rounded-xl border border-border bg-popover p-3 text-popover-foreground shadow-xl ring-1 ring-border/50"
                        >
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Coluna 1: Visualização das tabelas + Fontes -->
                                <div class="space-y-1">
                                    <div
                                        class="px-1.5 pb-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Visualização das tabelas
                                    </div>
                                    <button
                                        type="button"
                                        class="flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left text-xs transition hover:bg-muted"
                                        @click="
                                            emit('setViewMode', 'all');
                                            viewMenuOpen = false;
                                        "
                                    >
                                        <ChevronsDown
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                        />
                                        <span>
                                            <strong class="block font-semibold">
                                                Exibir todas as tabelas
                                            </strong>
                                            <small
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                Resultados e considerações da
                                                Consucal
                                            </small>
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        class="flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left text-xs transition hover:bg-muted"
                                        @click="
                                            emit('setViewMode', 'none');
                                            viewMenuOpen = false;
                                        "
                                    >
                                        <ChevronsUp
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                        />
                                        <span>
                                            <strong class="block font-semibold">
                                                Ocultar todas as tabelas
                                            </strong>
                                            <small
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                Manter as considerações da
                                                Consucal
                                            </small>
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        class="flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left text-xs transition hover:bg-muted"
                                        @click="
                                            emit('setViewMode', 'compact');
                                            viewMenuOpen = false;
                                        "
                                    >
                                        <PanelTopClose
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                        />
                                        <span>
                                            <strong class="block font-semibold">
                                                Ocultar todo o conteúdo
                                            </strong>
                                            <small
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                Apenas títulos e indicadores;
                                                oculta também os comentários
                                            </small>
                                        </span>
                                    </button>

                                    <hr class="my-2 border-border" />

                                    <div
                                        class="px-1.5 pb-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Tamanho da fonte
                                    </div>
                                    <div class="space-y-2.5 px-1.5">
                                        <div>
                                            <div
                                                class="flex justify-between text-xs"
                                            >
                                                <span>Tabelas</span>
                                                <span
                                                    class="font-semibold text-primary tabular-nums"
                                                >
                                                    {{ preferences.tableFont }}
                                                    px
                                                </span>
                                            </div>
                                            <input
                                                :value="preferences.tableFont"
                                                type="range"
                                                min="11"
                                                max="16"
                                                step="1"
                                                class="mt-1 w-full cursor-pointer accent-primary"
                                                @input="
                                                    updatePref(
                                                        'tableFont',
                                                        Number(
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).value,
                                                        ),
                                                    )
                                                "
                                            />
                                        </div>
                                        <div>
                                            <div
                                                class="flex justify-between text-xs"
                                            >
                                                <span
                                                    >Considerações
                                                    Consucal</span
                                                >
                                                <span
                                                    class="font-semibold text-primary tabular-nums"
                                                >
                                                    {{
                                                        preferences.technicalFont
                                                    }}
                                                    px
                                                </span>
                                            </div>
                                            <input
                                                :value="
                                                    preferences.technicalFont
                                                "
                                                type="range"
                                                min="12"
                                                max="18"
                                                step="1"
                                                class="mt-1 w-full cursor-pointer accent-primary"
                                                @input="
                                                    updatePref(
                                                        'technicalFont',
                                                        Number(
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).value,
                                                        ),
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Coluna 2: Tipos de Dados + Colunas -->
                                <div class="space-y-1">
                                    <div
                                        class="px-1.5 pb-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Tipos de dados
                                    </div>
                                    <label
                                        v-for="code in PARAMETER_CATEGORY_CODES"
                                        :key="code"
                                        class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-xs hover:bg-muted"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span
                                                class="inline-flex min-w-9 justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                                :class="
                                                    PARAMETER_CATEGORY_META[
                                                        code
                                                    ].badgeClass
                                                "
                                            >
                                                {{ code }}
                                            </span>
                                            <span>{{
                                                PARAMETER_CATEGORY_META[code]
                                                    .label
                                            }}</span>
                                        </span>
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            :checked="
                                                preferences.categories[code]
                                            "
                                            class="size-4 cursor-pointer accent-primary"
                                            @change="toggleCategory(code)"
                                        />
                                    </label>
                                    <p
                                        class="px-2 pt-1 text-[11px] leading-snug text-muted-foreground"
                                    >
                                        Altera apenas as linhas exibidas. Os
                                        indicadores consideram todos os dados.
                                    </p>

                                    <hr class="my-2 border-border" />

                                    <div
                                        class="px-1.5 pb-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Colunas da tabela
                                    </div>
                                    <label
                                        class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-xs hover:bg-muted"
                                    >
                                        <span>Exibir TAGs</span>
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            :checked="preferences.showTags"
                                            class="size-4 cursor-pointer accent-primary"
                                            @change="
                                                updatePref(
                                                    'showTags',
                                                    !preferences.showTags,
                                                )
                                            "
                                        />
                                    </label>
                                    <label
                                        class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-xs hover:bg-muted"
                                    >
                                        <span>Exibir Média</span>
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            :checked="preferences.showMean"
                                            class="size-4 cursor-pointer accent-primary"
                                            @change="
                                                updatePref(
                                                    'showMean',
                                                    !preferences.showMean,
                                                )
                                            "
                                        />
                                    </label>
                                    <label
                                        class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-xs hover:bg-muted"
                                    >
                                        <span>Exibir Gráficos</span>
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            :checked="preferences.showCharts"
                                            class="size-4 cursor-pointer accent-primary"
                                            @change="
                                                updatePref(
                                                    'showCharts',
                                                    !preferences.showCharts,
                                                )
                                            "
                                        />
                                    </label>
                                </div>
                            </div>

                            <hr class="my-2.5 border-border" />

                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted"
                                @click="
                                    emit('resetPreferences');
                                    viewMenuOpen = false;
                                "
                            >
                                <RotateCcw class="size-3.5" />
                                <span>Restaurar padrão</span>
                            </button>
                        </div>
                    </div>

                    <!-- Ir para sistema -->
                    <div ref="navigateMenuRef" class="relative">
                        <button
                            type="button"
                            :aria-expanded="navigateMenuOpen"
                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-[11.5px] font-medium text-foreground transition hover:bg-muted"
                            @click="navigateMenuOpen = !navigateMenuOpen"
                        >
                            <Search class="size-3.5 text-muted-foreground" />
                            <span>Ir para sistema</span>
                            <ChevronDown
                                class="size-3.5 text-muted-foreground"
                            />
                        </button>

                        <div
                            v-if="navigateMenuOpen"
                            class="absolute top-full left-0 z-50 mt-2 w-80 rounded-xl border border-border bg-popover p-3 text-popover-foreground shadow-xl ring-1 ring-border/50"
                        >
                            <div
                                class="mb-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Sistemas do relatório
                            </div>
                            <input
                                v-model="systemSearch"
                                type="search"
                                placeholder="Digite o nome do sistema…"
                                aria-label="Buscar sistema"
                                class="mb-2 h-8 w-full rounded-lg border border-input bg-background px-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-hidden"
                            />
                            <div class="max-h-64 space-y-1 overflow-y-auto">
                                <button
                                    v-for="row in filteredNavSystems"
                                    :key="row.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs transition hover:bg-muted"
                                    @click="handleNavigateFromMenu(row.id)"
                                >
                                    <span class="min-w-0 flex-1 font-medium">
                                        {{ row.name }}
                                    </span>
                                    <span
                                        class="shrink-0 text-[11px] font-semibold"
                                        :class="
                                            row.metrics.deviations > 0
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        <template v-if="row.hideData">
                                            Tabela oculta
                                        </template>
                                        <template
                                            v-else-if="!row.metrics.total"
                                        >
                                            Sem dados
                                        </template>
                                        <template
                                            v-else-if="!row.metrics.eligible"
                                        >
                                            Não avaliável
                                        </template>
                                        <template v-else>
                                            {{ row.metrics.deviations }}
                                            {{
                                                row.metrics.deviations === 1
                                                    ? 'desvio'
                                                    : 'desvios'
                                            }}
                                        </template>
                                        ↗
                                    </span>
                                </button>
                                <p
                                    v-if="filteredNavSystems.length === 0"
                                    class="py-3 text-center text-xs text-muted-foreground"
                                >
                                    Nenhum sistema encontrado.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botão Conformidade dos sistemas -->
                <button
                    type="button"
                    :aria-expanded="summaryOpen"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-[11.5px] font-medium text-foreground transition hover:bg-muted"
                    @click="summaryOpen = !summaryOpen"
                >
                    <ListChecks class="size-3.5 text-muted-foreground" />
                    <span>Conformidade dos sistemas</span>
                    <strong
                        class="rounded bg-[var(--report-soft-blue)] px-1.5 py-0.5 text-[11.5px] font-bold text-primary tabular-nums"
                        title="Percentual dos resultados avaliáveis em conformidade"
                    >
                        {{ overallConformity }}
                    </strong>
                    <ChevronDown
                        class="size-3.5 text-muted-foreground transition-transform duration-200"
                        :class="summaryOpen ? 'rotate-180' : ''"
                    />
                </button>
            </div>

            <!-- Painel de Conformidade dos Sistemas -->
            <ClientReportSummaryPanel
                v-if="summaryOpen"
                :date-reference="dateReference"
                :rows="summaryRows"
                :totals="summaryTotals"
                @navigate-system="handleNavigateFromMenu"
            />

            <!-- Central de Comentários / Respostas -->
            <section class="border-t border-[var(--report-interaction-border)]">
                <button
                    type="button"
                    :aria-expanded="centralOpen"
                    class="flex w-full items-center justify-between gap-3 bg-[var(--report-interaction-bg)] px-3.5 py-2 text-left transition hover:bg-[var(--report-interaction-hover)]"
                    :class="centralOpen ? '' : 'rounded-b-xl'"
                    @click="centralOpen = !centralOpen"
                >
                    <span class="flex min-w-0 flex-1 items-center gap-2.5">
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--report-interaction-icon)] text-[var(--report-interaction-ink)]"
                        >
                            <MessageSquare class="size-3.5" />
                        </span>
                        <span
                            class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-0.5"
                        >
                            <strong
                                class="text-[11.5px] font-semibold text-[var(--report-interaction-ink)]"
                            >
                                Comentários / Respostas
                            </strong>
                            <span
                                class="text-[11px] text-[var(--report-interaction-ink)] opacity-85"
                            >
                                <template v-if="centralComments.length > 0">
                                    {{ centralComments.length }}
                                    {{
                                        centralComments.length === 1
                                            ? 'comentário registrado'
                                            : 'comentários registrados'
                                    }}
                                    · {{ blocksWithCommentsCount }}
                                    {{
                                        blocksWithCommentsCount === 1
                                            ? 'bloco'
                                            : 'blocos'
                                    }}
                                    com interações
                                </template>
                                <template v-else>
                                    Nenhuma interação registrada
                                </template>
                            </span>
                            <span
                                v-if="latestCommentDate"
                                class="border-l border-[var(--report-interaction-border)] pl-3 text-[11px] text-[var(--report-interaction-ink)]"
                            >
                                Última interação: {{ latestCommentDate }}
                            </span>
                        </span>
                    </span>

                    <span class="flex shrink-0 items-center gap-2">
                        <span
                            class="inline-flex min-w-6 items-center justify-center rounded-full bg-[var(--report-interaction-count)] px-2 py-0.5 text-[11px] font-bold text-[var(--report-interaction-count-ink)]"
                        >
                            {{ centralComments.length }}
                        </span>
                        <ChevronDown
                            class="size-4 text-[var(--report-interaction-ink)] transition-transform duration-200"
                            :class="centralOpen ? 'rotate-180' : ''"
                        />
                    </span>
                </button>

                <div
                    v-if="centralOpen"
                    class="max-h-72 space-y-2 overflow-y-auto border-t border-[var(--report-interaction-border)] bg-card p-3.5"
                >
                    <div
                        v-if="orderedCentralComments.length === 0"
                        class="py-4 text-center text-xs text-muted-foreground"
                    >
                        Nenhuma interação registrada neste relatório ainda.
                    </div>

                    <article
                        v-for="comment in orderedCentralComments"
                        :key="comment.id"
                        class="rounded-lg border border-[var(--report-interaction-border)] bg-[var(--report-interaction-bg)] p-3 text-xs"
                    >
                        <div
                            class="mb-1 flex flex-wrap items-center justify-between gap-2"
                        >
                            <span class="font-bold text-primary">
                                {{ comment.systemName }}
                            </span>
                            <span class="text-[11px] text-muted-foreground">
                                {{ formatDateTimeBr(comment.createdAt) }}
                            </span>
                        </div>
                        <div class="mb-1 text-[11px] text-muted-foreground">
                            <strong class="text-foreground">{{
                                comment.author
                            }}</strong>
                            ·
                            {{ comment.isConsucal ? 'Consucal' : 'Cliente' }}
                        </div>
                        <p class="whitespace-pre-wrap text-foreground/90">
                            {{ comment.body }}
                        </p>
                        <div class="mt-2 text-right">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline"
                                @click="
                                    handleNavigateFromMenu(comment.systemId)
                                "
                            >
                                <span>Ir para o bloco</span>
                                <ArrowUpRight class="size-3" />
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </nav>
</template>
