<script setup lang="ts">
import { Bot, Check, Layers, Loader2, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import ReportDataTable from '@/features/reports/components/ReportDataTable.vue';
import ReportEditorLauncher from '@/features/reports/components/ReportEditorLauncher.vue';
import type { ReportSystem } from '@/features/reports/types';
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
    system: SystemSection;
    groupedChildren: SystemSection[];
    combinedTitle: string;
    canGroup: boolean;
    isHighlighted: boolean;
    systemDates: { start_date: string; end_date: string };
    isLoadingData: boolean;
    systemData?: ReportData;
    currentTeam: Team;
    systems: ReportSystem[];
    reportDateReference: string;
    resolvingSuggestions: Record<number, boolean>;
}>();

const emit = defineEmits<{
    ungroupChild: [child: SystemSection];
    openGroupModal: [system: SystemSection];
    toggleSystem: [system: SystemSection, checked: boolean];
    loadData: [
        system: SystemSection,
        force?: boolean,
        dates?: { start_date: string; end_date: string },
    ];
    chart: [system: SystemSection, parameterId: number, type: 'line' | 'bar'];
    resolveSuggestion: [suggestionId: number, decision: 'accept' | 'reject'];
    editorSaved: [];
}>();

const localStartDate = ref(props.systemDates.start_date);
const localEndDate = ref(props.systemDates.end_date);

watch(
    () => props.systemDates,
    (dates) => {
        localStartDate.value = dates.start_date;
        localEndDate.value = dates.end_date;
    },
    { deep: true },
);
</script>

<template>
    <section
        :data-report-system-id="system.id"
        class="overflow-hidden rounded-3xl border bg-card shadow-sm transition duration-300"
        :class="[
            system.item?.show_data_results
                ? 'border-emerald-500/60 bg-emerald-500/[0.015] shadow-sm ring-1 ring-emerald-500/25'
                : 'border-border/70 opacity-80 hover:opacity-100',
            isHighlighted &&
                'shadow-xl ring-4 shadow-violet-950/15 ring-violet-400/50',
        ]"
    >
        <header
            class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-2.5"
            :class="
                system.item?.show_data_results
                    ? 'border-emerald-500/20 bg-emerald-500/10 text-foreground'
                    : 'border-border/50 bg-muted/20 text-muted-foreground'
            "
        >
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-semibold text-foreground">
                    {{ combinedTitle }}
                </h2>
                <span
                    v-if="system.item?.show_data_results"
                    class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-500/30 dark:text-emerald-300"
                >
                    Habilitado
                </span>
                <span
                    v-else
                    class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                >
                    Desabilitado
                </span>

                <!-- Ungrouping chips for child systems -->
                <div
                    v-if="groupedChildren.length > 0"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <span
                        v-for="child in groupedChildren"
                        :key="child.id"
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800 ring-1 ring-emerald-500/30 dark:text-emerald-300"
                    >
                        {{ child.name }}
                        <button
                            type="button"
                            class="ml-0.5 inline-flex size-3.5 items-center justify-center rounded-full transition hover:bg-emerald-500/30 hover:text-red-600"
                            title="Desagrupar sistema"
                            @click="emit('ungroupChild', child)"
                        >
                            <X class="size-2.5" />
                        </button>
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <button
                    v-if="canGroup"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1 text-xs font-medium text-foreground shadow-2xs transition hover:bg-muted"
                    title="Agrupar este sistema no primeiro sistema habilitado"
                    @click="emit('openGroupModal', system)"
                >
                    <Layers class="size-3.5 text-muted-foreground" />
                    Agrupar
                </button>

                <span class="text-xs font-medium text-foreground">
                    Habilitado
                </span>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="system.item?.show_data_results ?? false"
                    :disabled="
                        Boolean(
                            system.item?.comment &&
                            system.item.comment.replace(/<[^>]+>/g, '').trim(),
                        )
                    "
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="
                        system.item?.show_data_results
                            ? 'bg-emerald-600'
                            : 'bg-muted-foreground/30'
                    "
                    @click="
                        emit(
                            'toggleSystem',
                            system,
                            !system.item?.show_data_results,
                        )
                    "
                >
                    <span
                        aria-hidden="true"
                        class="pointer-events-none inline-block size-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                        :class="
                            system.item?.show_data_results
                                ? 'translate-x-5'
                                : 'translate-x-0'
                        "
                    />
                </button>
            </div>
        </header>

        <div
            v-if="system.item?.show_data_results && !system.item.hide_data"
            class="p-4"
        >
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/70 bg-muted/20 p-3"
            >
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-medium text-muted-foreground"
                        >Período:</span
                    >
                    <input
                        type="date"
                        v-model="localStartDate"
                        class="h-8 rounded-lg border border-input bg-background px-2 text-xs text-foreground shadow-sm"
                    />
                    <span class="text-muted-foreground">até</span>
                    <input
                        type="date"
                        v-model="localEndDate"
                        class="h-8 rounded-lg border border-input bg-background px-2 text-xs text-foreground shadow-sm"
                    />
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                        :disabled="isLoadingData"
                        @click="
                            emit('loadData', system, true, {
                                start_date: localStartDate,
                                end_date: localEndDate,
                            })
                        "
                    >
                        <Loader2
                            v-if="isLoadingData"
                            class="size-3 animate-spin"
                        />
                        <span>Atualizar</span>
                    </button>
                </div>
            </div>

            <div v-if="isLoadingData" class="grid h-32 place-items-center">
                <Loader2 class="size-6 animate-spin text-emerald-600" />
            </div>
            <ReportDataTable
                v-else-if="systemData"
                :parameters="systemData.parameters"
                :rows="systemData.rows"
                @chart="
                    (parameterId, type) =>
                        emit('chart', system, parameterId, type)
                "
            />
        </div>

        <div
            v-if="system.item?.show_data_results"
            class="border-t border-blue-100 bg-blue-50/35 p-4 dark:border-blue-950 dark:bg-blue-950/10"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-foreground">
                        Considerações Consucal
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Edite o texto e os gráficos específicos deste sistema.
                    </p>
                </div>
                <ReportEditorLauncher
                    :current-team="currentTeam"
                    :systems="systems"
                    :selected-system-ids="[system.id]"
                    :default-date="reportDateReference"
                    button-label="Editar consideração"
                    @saved="emit('editorSaved')"
                />
            </div>
            <div
                v-if="system.item.comment"
                class="prose prose-sm dark:prose-invert mt-4 max-w-none rounded-2xl border bg-card p-4 text-foreground"
                v-html="system.item.comment"
            />
            <p
                v-else
                class="mt-4 rounded-2xl border border-dashed bg-card/60 p-4 text-xs text-muted-foreground"
            >
                Nenhuma consideração registrada para este sistema.
            </p>
        </div>

        <div
            v-if="system.item?.suggestions.length"
            class="grid gap-2 border-t bg-violet-50/60 p-4 dark:bg-violet-950/15"
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
                        <p class="mt-1 text-emerald-700 dark:text-emerald-300">
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
                            emit('resolveSuggestion', suggestion.id, 'accept')
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
                            emit('resolveSuggestion', suggestion.id, 'reject')
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
    </section>
</template>
