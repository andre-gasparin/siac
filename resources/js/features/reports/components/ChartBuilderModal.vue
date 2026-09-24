<script setup lang="ts">
import {
    BarChart3,
    Bookmark,
    Check,
    ChevronDown,
    Loader2,
    Plus,
    Search,
    SlidersHorizontal,
    Star,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type {
    ChartFavoriteTemplate,
    ReportChartConfig,
    ReportChartSeriesConfig,
    ReportParameter,
} from '@/features/reports/types';
import { search as parameterSearchRoute } from '@/routes/parameters';
import { data as chartDataRoute } from '@/routes/reports/charts';
import EChartRenderer from '@/shared/components/charts/EChartRenderer.vue';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';

interface EditableSeries extends ReportChartSeriesConfig {
    system_name: string;
}

const props = defineProps<{
    open: boolean;
    teamSlug: string;
    reportDate: string;
    initialChart?: ReportChartConfig | null;
}>();

const emit = defineEmits<{
    close: [];
    insert: [chart: ReportChartConfig];
}>();

const palette = ['#2563EB', '#E11D48', '#059669', '#D97706', '#7C3AED'];
const title = ref('Gráfico do relatório');
const height = ref(320);
const startDate = ref('');
const endDate = ref('');
const searchQuery = ref('');
const searchOpen = ref(false);
const searchContainer = ref<HTMLElement | null>(null);
const favoritesContainer = ref<HTMLElement | null>(null);
const searchResults = ref<ReportParameter[]>([]);
const series = ref<EditableSeries[]>([]);
const previewSeries = ref<ChartSeries[]>([]);
const isSearching = ref(false);
const isPreviewing = ref(false);
const previewError = ref('');

// Favorites management state
const favoriteTemplates = ref<ChartFavoriteTemplate[]>([]);
const isLoadingFavorites = ref(false);
const isSavingFavorite = ref(false);
const favoritesDropdownOpen = ref(false);
const selectedFavoriteId = ref<number | null>(null);
const isFavorite = ref(false);
const favoriteSuccessMessage = ref('');
const showDeleteConfirm = ref(false);
const deleteConfirmContainer = ref<HTMLElement | null>(null);

let searchTimer: ReturnType<typeof setTimeout> | undefined;
let previewTimer: ReturnType<typeof setTimeout> | undefined;

function closeOutsideClicks(event: PointerEvent) {
    if (
        searchOpen.value &&
        !searchContainer.value?.contains(event.target as Node)
    ) {
        searchOpen.value = false;
    }

    if (
        favoritesDropdownOpen.value &&
        !favoritesContainer.value?.contains(event.target as Node)
    ) {
        favoritesDropdownOpen.value = false;
    }

    if (
        showDeleteConfirm.value &&
        !deleteConfirmContainer.value?.contains(event.target as Node)
    ) {
        showDeleteConfirm.value = false;
    }
}

async function confirmDeleteSelectedFavorite() {
    if (!selectedFavoriteId.value) {
        return;
    }

    const idToDelete = selectedFavoriteId.value;
    showDeleteConfirm.value = false;
    await deleteFavoriteTemplate(idToDelete);
    favoriteSuccessMessage.value = 'Favorito excluído com sucesso!';
    setTimeout(() => {
        favoriteSuccessMessage.value = '';
    }, 3000);
}

const canInsert = computed(
    () =>
        series.value.length > 0 &&
        startDate.value !== '' &&
        endDate.value !== '' &&
        startDate.value <= endDate.value,
);

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function fetchFavoriteTemplates() {
    isLoadingFavorites.value = true;

    try {
        const response = await fetch(
            `/${props.teamSlug}/reports/chart-templates`,
            {
                headers: { Accept: 'application/json' },
            },
        );

        if (response.ok) {
            favoriteTemplates.value = await response.json();
        }
    } catch (e) {
        console.error('Error fetching favorite templates:', e);
    } finally {
        isLoadingFavorites.value = false;
    }
}

function loadFavoriteTemplate(template: ChartFavoriteTemplate) {
    selectedFavoriteId.value = template.id;
    isFavorite.value = true;
    title.value = template.name;
    height.value = template.options?.height ?? 320;

    series.value = template.series.map((s) => ({
        parameter_id: s.parameter_id,
        system_name: s.system_name || 'Sistema',
        label: s.label,
        chart_type: s.chart_type || 'line',
        color: s.color || palette[0],
        stroke_width: s.stroke_width ?? 2,
        axis_position: s.axis_position || 'left',
        min_val: s.min_val ?? null,
        max_val: s.max_val ?? null,
        show_points: s.show_points ?? true,
        show_values: s.show_values ?? false,
    }));

    favoritesDropdownOpen.value = false;
}

async function saveFavoriteTemplate(asNew = false) {
    if (series.value.length === 0 || !title.value.trim()) {
        return;
    }

    isSavingFavorite.value = true;
    favoriteSuccessMessage.value = '';

    const payload = {
        name: title.value.trim(),
        is_favorite: true,
        options: { height: height.value },
        series: series.value.map(serializeSeries),
    };

    try {
        const targetId = !asNew ? selectedFavoriteId.value : null;
        const url = targetId
            ? `/${props.teamSlug}/reports/chart-templates/${targetId}`
            : `/${props.teamSlug}/reports/chart-templates`;
        const method = targetId ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(payload),
        });

        if (response.ok) {
            const savedTemplate: ChartFavoriteTemplate = await response.json();
            selectedFavoriteId.value = savedTemplate.id;
            isFavorite.value = true;
            favoriteSuccessMessage.value = targetId
                ? 'Favorito atualizado!'
                : 'Salvo nos favoritos!';
            await fetchFavoriteTemplates();

            setTimeout(() => {
                favoriteSuccessMessage.value = '';
            }, 3000);
        }
    } catch (e) {
        console.error('Error saving favorite template:', e);
    } finally {
        isSavingFavorite.value = false;
    }
}

async function deleteFavoriteTemplate(templateId: number) {
    try {
        const response = await fetch(
            `/${props.teamSlug}/reports/chart-templates/${templateId}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        if (response.ok) {
            if (selectedFavoriteId.value === templateId) {
                selectedFavoriteId.value = null;
                isFavorite.value = false;
            }

            await fetchFavoriteTemplates();
        }
    } catch (e) {
        console.error('Error deleting favorite template:', e);
    }
}

function reset() {
    selectedFavoriteId.value = null;
    isFavorite.value = false;
    favoriteSuccessMessage.value = '';
    void fetchFavoriteTemplates();

    if (props.initialChart) {
        title.value = props.initialChart.title;
        height.value = props.initialChart.height;
        startDate.value = props.initialChart.startDate;
        endDate.value = props.initialChart.endDate;
        searchQuery.value = '';
        searchOpen.value = false;
        series.value = props.initialChart.series.map((item) => ({
            ...item,
            system_name: 'Parâmetro do gráfico',
        }));
        previewSeries.value = [];
        previewError.value = '';
        void searchParameters();

        return;
    }

    const end = props.reportDate;
    const start = new Date(`${end}T12:00:00`);
    start.setDate(start.getDate() - 6);
    title.value = 'Gráfico do relatório';
    height.value = 320;
    startDate.value = start.toISOString().slice(0, 10);
    endDate.value = end;
    searchQuery.value = '';
    searchOpen.value = true;
    series.value = [];
    previewSeries.value = [];
    previewError.value = '';
    void searchParameters();
}

async function searchParameters() {
    isSearching.value = true;

    try {
        const response = await fetch(
            parameterSearchRoute.url(
                { current_team: props.teamSlug },
                { query: { q: searchQuery.value.trim() } },
            ),
            { headers: { Accept: 'application/json' } },
        );
        searchResults.value = response.ok ? await response.json() : [];
        series.value.forEach((item) => {
            const parameter = searchResults.value.find(
                (result) => result.id === item.parameter_id,
            );

            if (parameter) {
                item.system_name = parameter.system_name ?? 'Sistema';
            }
        });
    } finally {
        isSearching.value = false;
    }
}

function addParameter(parameter: ReportParameter) {
    if (
        series.value.length >= 5 ||
        series.value.some((item) => item.parameter_id === parameter.id)
    ) {
        return;
    }

    const index = series.value.length;
    series.value.push({
        parameter_id: parameter.id,
        label: parameter.name,
        system_name: parameter.system_name ?? 'Sistema',
        chart_type: 'line',
        color: palette[index],
        stroke_width: 2,
        axis_position: index === 1 ? 'right' : 'left',
        min_val: null,
        max_val: null,
        show_points: true,
        show_values: false,
    });
    searchQuery.value = '';
    searchOpen.value = false;
}

function serializeSeries(item: EditableSeries): ReportChartSeriesConfig {
    return {
        parameter_id: item.parameter_id,
        label: item.label,
        chart_type: item.chart_type,
        color: item.color,
        stroke_width: item.stroke_width,
        axis_position: item.axis_position,
        min_val: item.min_val,
        max_val: item.max_val,
        show_points: item.show_points,
        show_values: item.show_values,
    };
}

async function refreshPreview() {
    previewError.value = '';

    if (!canInsert.value) {
        previewSeries.value = [];

        return;
    }

    isPreviewing.value = true;

    try {
        const response = await fetch(
            chartDataRoute.url({ current_team: props.teamSlug }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    start_date: startDate.value,
                    end_date: endDate.value,
                    series: series.value.map(serializeSeries),
                }),
            },
        );

        if (!response.ok) {
            previewSeries.value = [];
            previewError.value = 'Não foi possível carregar a prévia.';

            return;
        }

        previewSeries.value = (await response.json()).series;
    } finally {
        isPreviewing.value = false;
    }
}

function insertChart() {
    if (!canInsert.value) {
        return;
    }

    emit('insert', {
        id: props.initialChart?.id ?? crypto.randomUUID(),
        title: title.value.trim() || 'Gráfico',
        height: height.value,
        startDate: startDate.value,
        endDate: endDate.value,
        teamSlug: props.teamSlug,
        series: series.value.map(serializeSeries),
    });
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        }
    },
);

watch(searchQuery, () => {
    searchOpen.value = true;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => void searchParameters(), 250);
});

watch(
    [series, startDate, endDate],
    () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(() => void refreshPreview(), 250);
    },
    { deep: true },
);

onMounted(() => {
    document.addEventListener('pointerdown', closeOutsideClicks);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeOutsideClicks);
    clearTimeout(searchTimer);
    clearTimeout(previewTimer);
});
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm"
        data-test="chart-builder"
        @click.self="emit('close')"
    >
        <section
            class="grid h-[94vh] max-h-[94vh] min-h-0 w-full max-w-6xl overflow-hidden rounded-2xl border bg-card shadow-2xl lg:grid-cols-[minmax(430px,0.9fr)_minmax(0,1.4fr)]"
        >
            <div class="min-h-0 overflow-y-auto border-r">
                <header
                    class="sticky top-0 z-10 flex items-center justify-between border-b bg-card/95 px-5 py-4 backdrop-blur"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-blue-600 text-white shadow-sm"
                        >
                            <BarChart3 class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-semibold">
                                {{
                                    initialChart
                                        ? 'Editar gráfico'
                                        : 'Criar gráfico'
                                }}
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Monte as séries e acompanhe a prévia ao vivo
                            </p>
                        </div>
                    </div>
                    <button
                        class="rounded-lg p-2 hover:bg-muted"
                        title="Fechar"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="grid gap-5 p-5">
                    <!-- Favorite Charts Selection -->
                    <div ref="favoritesContainer" class="relative">
                        <div
                            class="mb-1 flex items-center justify-between gap-1"
                        >
                            <span
                                class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                <Bookmark
                                    class="size-3.5 shrink-0 text-amber-500"
                                />
                                Gráficos favoritos
                            </span>
                            <span
                                v-if="favoriteTemplates.length"
                                class="shrink-0 text-[11px] text-muted-foreground"
                            >
                                {{ favoriteTemplates.length }} salvo(s)
                            </span>
                        </div>
                        <button
                            type="button"
                            data-test="favorite-charts-dropdown"
                            class="flex h-10 w-full items-center justify-between rounded-lg border bg-background px-3 text-sm transition-colors hover:bg-muted/50"
                            @click="
                                favoritesDropdownOpen = !favoritesDropdownOpen
                            "
                        >
                            <span
                                class="flex min-w-0 flex-1 items-center gap-2 text-xs font-medium"
                            >
                                <Star
                                    v-if="selectedFavoriteId"
                                    class="size-4 shrink-0 fill-amber-500 text-amber-500"
                                />
                                <Star
                                    v-else
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                <span class="truncate">
                                    {{
                                        selectedFavoriteId
                                            ? favoriteTemplates.find(
                                                  (f) =>
                                                      f.id ===
                                                      selectedFavoriteId,
                                              )?.name || title
                                            : 'Procurar ou selecionar um favorito...'
                                    }}
                                </span>
                            </span>
                            <ChevronDown
                                class="ml-1 size-4 shrink-0 text-muted-foreground transition-transform"
                                :class="{ 'rotate-180': favoritesDropdownOpen }"
                            />
                        </button>

                        <div
                            v-if="favoritesDropdownOpen"
                            class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border bg-popover p-1 shadow-xl"
                        >
                            <div
                                v-if="isLoadingFavorites"
                                class="flex items-center justify-center gap-2 p-3 text-xs text-muted-foreground"
                            >
                                <Loader2
                                    class="size-4 animate-spin text-blue-600"
                                />
                                Carregando favoritos...
                            </div>
                            <template v-else-if="favoriteTemplates.length">
                                <div
                                    v-for="fav in favoriteTemplates"
                                    :key="fav.id"
                                    data-test="favorite-option"
                                    class="group flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-xs transition-colors hover:bg-muted"
                                    :class="{
                                        'bg-amber-500/10 font-medium text-amber-900 dark:text-amber-200':
                                            selectedFavoriteId === fav.id,
                                    }"
                                    @click="loadFavoriteTemplate(fav)"
                                >
                                    <div class="min-w-0 flex-1 pr-2">
                                        <div
                                            class="flex items-center gap-1.5 truncate font-semibold"
                                        >
                                            <Star
                                                class="size-3.5 shrink-0 fill-amber-500 text-amber-500"
                                            />
                                            <span class="truncate">{{
                                                fav.name
                                            }}</span>
                                        </div>
                                        <p
                                            class="truncate text-[11px] text-muted-foreground"
                                        >
                                            {{ fav.series.length }} série(s) ·
                                            {{
                                                fav.series
                                                    .map((s) => s.label)
                                                    .join(', ')
                                            }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-muted-foreground transition-opacity hover:bg-red-50 hover:text-red-600 lg:opacity-0 lg:group-hover:opacity-100 dark:hover:bg-red-950/40"
                                        title="Excluir favorito"
                                        @click.stop="
                                            deleteFavoriteTemplate(fav.id)
                                        "
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </template>
                            <div
                                v-else
                                class="p-3 text-center text-xs text-muted-foreground"
                            >
                                Nenhum gráfico favorito salvo nesta empresa.
                            </div>
                        </div>
                    </div>

                    <!-- Title & Favorite Controls -->
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-xs font-medium">Título</label>
                            <span
                                v-if="favoriteSuccessMessage"
                                class="flex items-center gap-1 text-xs font-semibold text-emerald-600"
                            >
                                <Check class="size-3.5" />
                                {{ favoriteSuccessMessage }}
                            </span>
                        </div>
                        <input
                            v-model="title"
                            data-test="chart-title"
                            class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                            placeholder="Ex: Gráfico de Vazão e pH"
                        />
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 pt-0.5"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    data-test="save-favorite-btn"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-xs font-medium transition-colors"
                                    :class="
                                        selectedFavoriteId || isFavorite
                                            ? 'border-amber-500/50 bg-amber-500/10 text-amber-700 hover:bg-amber-500/20 dark:text-amber-300'
                                            : 'bg-background text-muted-foreground hover:bg-muted'
                                    "
                                    title="Salvar configurações como favorito"
                                    :disabled="
                                        isSavingFavorite || series.length === 0
                                    "
                                    @click="
                                        selectedFavoriteId
                                            ? saveFavoriteTemplate(false)
                                            : saveFavoriteTemplate(true)
                                    "
                                >
                                    <Loader2
                                        v-if="isSavingFavorite"
                                        class="size-3.5 animate-spin text-amber-600"
                                    />
                                    <Star
                                        v-else
                                        class="size-3.5 shrink-0"
                                        :class="{
                                            'fill-amber-500 text-amber-500':
                                                selectedFavoriteId ||
                                                isFavorite,
                                        }"
                                    />
                                    <span>{{
                                        selectedFavoriteId
                                            ? 'Atualizar Favorito'
                                            : 'Salvar nos Favoritos'
                                    }}</span>
                                </button>

                                <div
                                    v-if="selectedFavoriteId"
                                    ref="deleteConfirmContainer"
                                    class="relative"
                                >
                                    <button
                                        type="button"
                                        data-test="delete-favorite-btn"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-red-200 bg-red-50/60 px-2.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-950/60"
                                        title="Excluir este gráfico favorito"
                                        @click="
                                            showDeleteConfirm =
                                                !showDeleteConfirm
                                        "
                                    >
                                        <Trash2 class="size-3.5 shrink-0" />
                                        <span>Excluir</span>
                                    </button>

                                    <div
                                        v-if="showDeleteConfirm"
                                        class="absolute top-full left-0 z-40 mt-1.5 w-52 rounded-xl border bg-popover p-3 text-xs shadow-xl"
                                    >
                                        <p
                                            class="mb-2 font-medium text-foreground"
                                        >
                                            Excluir este gráfico favorito?
                                        </p>
                                        <div
                                            class="flex items-center justify-end gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-md border bg-background px-2.5 py-1 text-[11px] font-medium text-muted-foreground hover:bg-muted"
                                                @click="
                                                    showDeleteConfirm = false
                                                "
                                            >
                                                Cancelar
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md bg-red-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm hover:bg-red-700"
                                                @click="
                                                    confirmDeleteSelectedFavorite
                                                "
                                            >
                                                Confirmar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button
                                v-if="selectedFavoriteId"
                                type="button"
                                class="text-[11px] font-medium text-blue-600 hover:underline"
                                @click="saveFavoriteTemplate(true)"
                            >
                                Salvar como novo favorito
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1.5 text-xs font-medium">
                            Data inicial
                            <input
                                v-model="startDate"
                                type="date"
                                class="h-10 rounded-lg border bg-background px-3"
                            />
                        </label>
                        <label class="grid gap-1.5 text-xs font-medium">
                            Data final
                            <input
                                v-model="endDate"
                                type="date"
                                class="h-10 rounded-lg border bg-background px-3"
                            />
                        </label>
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xs font-semibold tracking-wide uppercase"
                            >
                                Sistema / parâmetro
                            </span>
                            <span class="text-[11px] text-muted-foreground">
                                {{ series.length }}/5 séries
                            </span>
                        </div>
                        <div ref="searchContainer" class="relative">
                            <Search
                                class="absolute top-3 left-3 size-4 text-muted-foreground"
                            />
                            <input
                                v-model="searchQuery"
                                class="h-10 w-full rounded-lg border bg-background pr-9 pl-9 text-sm"
                                placeholder="Busque sistema, parâmetro, código ou tag..."
                                @focus="searchOpen = true"
                            />
                            <Loader2
                                v-if="isSearching"
                                class="absolute top-3 right-3 size-4 animate-spin"
                            />
                            <div
                                v-if="searchOpen"
                                class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border bg-popover p-1 shadow-xl"
                            >
                                <button
                                    v-for="parameter in searchResults"
                                    :key="parameter.id"
                                    type="button"
                                    data-test="chart-parameter-option"
                                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-muted disabled:opacity-50"
                                    :disabled="
                                        series.some(
                                            (item) =>
                                                item.parameter_id ===
                                                parameter.id,
                                        )
                                    "
                                    @click="addParameter(parameter)"
                                >
                                    <span>
                                        <strong class="block text-xs">{{
                                            parameter.system_name
                                        }}</strong>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ parameter.name }}
                                            <template v-if="parameter.unit">
                                                · {{ parameter.unit }}
                                            </template>
                                        </span>
                                    </span>
                                    <Check
                                        v-if="
                                            series.some(
                                                (item) =>
                                                    item.parameter_id ===
                                                    parameter.id,
                                            )
                                        "
                                        class="size-4 text-emerald-600"
                                    />
                                    <Plus v-else class="size-4" />
                                </button>
                                <p
                                    v-if="
                                        !isSearching &&
                                        searchResults.length === 0
                                    "
                                    class="p-3 text-center text-xs text-muted-foreground"
                                >
                                    Nenhum parâmetro encontrado.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <article
                            v-for="(item, index) in series"
                            :key="item.parameter_id"
                            class="rounded-xl border bg-muted/20 p-3"
                        >
                            <div class="mb-3 flex items-start gap-2">
                                <input
                                    v-model="item.color"
                                    type="color"
                                    class="h-9 w-10 cursor-pointer rounded border bg-transparent"
                                    title="Cor da série"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-semibold">
                                        {{ item.system_name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ item.label }}
                                    </p>
                                </div>
                                <button
                                    class="rounded-lg p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                                    title="Remover série"
                                    @click="series.splice(index, 1)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <select
                                    v-model="item.chart_type"
                                    class="h-9 rounded-lg border bg-background px-2"
                                >
                                    <option value="line">Linha</option>
                                    <option value="bar">Barra</option>
                                    <option value="area">Área</option>
                                </select>
                                <select
                                    v-model="item.axis_position"
                                    class="h-9 rounded-lg border bg-background px-2"
                                >
                                    <option value="left">Eixo esquerdo</option>
                                    <option value="right">Eixo direito</option>
                                </select>
                                <label class="grid gap-1">
                                    Mínimo
                                    <input
                                        v-model.number="item.min_val"
                                        type="number"
                                        class="h-9 rounded-lg border bg-background px-2"
                                    />
                                </label>
                                <label class="grid gap-1">
                                    Máximo
                                    <input
                                        v-model.number="item.max_val"
                                        type="number"
                                        class="h-9 rounded-lg border bg-background px-2"
                                    />
                                </label>
                                <label class="grid gap-1">
                                    Espessura
                                    <input
                                        v-model.number="item.stroke_width"
                                        type="range"
                                        min="1"
                                        max="8"
                                    />
                                </label>
                                <div class="flex items-end gap-3 pb-1">
                                    <label class="flex items-center gap-1">
                                        <input
                                            v-model="item.show_points"
                                            type="checkbox"
                                        />Pontos
                                    </label>
                                    <label class="flex items-center gap-1">
                                        <input
                                            v-model="item.show_values"
                                            type="checkbox"
                                        />Valores
                                    </label>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>

            <div class="flex min-h-[520px] flex-col bg-muted/20 p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <SlidersHorizontal class="size-4 text-blue-600" />
                            Pré-visualização
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Toda alteração é aplicada automaticamente
                        </p>
                    </div>
                    <label class="flex items-center gap-2 text-xs">
                        Altura
                        <input
                            v-model.number="height"
                            type="range"
                            min="220"
                            max="620"
                            step="20"
                        />
                        <span class="w-12">{{ height }}px</span>
                    </label>
                </div>
                <div
                    class="relative flex flex-1 items-start justify-center overflow-auto rounded-2xl border bg-card pt-1 shadow-sm"
                >
                    <Loader2
                        v-if="isPreviewing"
                        class="absolute top-4 right-4 z-10 size-5 animate-spin text-blue-600"
                    />
                    <EChartRenderer
                        v-if="previewSeries.length"
                        class="w-full"
                        :series="previewSeries"
                        :height="height"
                    />
                    <div
                        v-else
                        class="max-w-sm text-center text-sm text-muted-foreground"
                    >
                        <BarChart3 class="mx-auto mb-3 size-10 opacity-30" />
                        <p class="font-medium text-foreground">
                            Selecione um sistema e parâmetro
                        </p>
                        <p class="mt-1 text-xs">
                            A prévia será carregada com os dados do período.
                        </p>
                        <p v-if="previewError" class="mt-2 text-red-600">
                            {{ previewError }}
                        </p>
                    </div>
                </div>
                <footer class="mt-4 flex justify-end gap-2">
                    <button
                        class="rounded-lg border bg-card px-4 py-2 text-sm"
                        @click="emit('close')"
                    >
                        Cancelar
                    </button>
                    <button
                        data-test="insert-chart"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm disabled:opacity-50"
                        :disabled="!canInsert"
                        @click="insertChart"
                    >
                        <Check v-if="initialChart" class="size-4" />
                        <Plus v-else class="size-4" />
                        {{
                            initialChart
                                ? 'Salvar alterações'
                                : 'Inserir no relatório'
                        }}
                    </button>
                </footer>
            </div>
        </section>
    </div>
</template>
