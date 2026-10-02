<script setup lang="ts">
import {
    AlertTriangle,
    Calculator,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Search,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type {
    AnalysisRow,
    ParameterAnalysisResult,
} from '@/features/statistical-analysis/types';

const props = defineProps<{
    result: ParameterAnalysisResult;
}>();

const emit = defineEmits<{
    openMemorial: [row: AnalysisRow];
}>();

const searchQuery = ref('');
const alertFilter = ref<'all' | 'alerts_only'>('all');
const currentPage = ref(1);
const perPage = ref(15);

const filteredRows = computed(() => {
    let rows = props.result.rows;

    if (alertFilter.value === 'alerts_only') {
        rows = rows.filter(
            (r) => r.ewma.alert_level > 0 || r.cusum.alert_level > 0,
        );
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        rows = rows.filter(
            (r) =>
                r.date.includes(q) ||
                r.time.includes(q) ||
                String(r.raw_value).includes(q),
        );
    }

    return rows;
});

const totalPages = computed(
    () => Math.ceil(filteredRows.value.length / perPage.value) || 1,
);

watch(
    [() => props.result.parameter_id, searchQuery, alertFilter, perPage],
    () => {
        currentPage.value = 1;
    },
    { flush: 'sync' },
);

watch(
    totalPages,
    (pages) => {
        currentPage.value = Math.min(currentPage.value, pages);
    },
    { flush: 'sync' },
);

const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;

    return filteredRows.value.slice(start, start + perPage.value);
});

function getAlertBadgeClass(level: number) {
    if (level === 0) {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20';
    }

    if (level === 1) {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20';
    }

    if (level === 2) {
        return 'bg-orange-500/10 text-orange-700 dark:text-orange-300 border-orange-500/20';
    }

    return 'bg-red-500/10 text-red-700 dark:text-red-300 border-red-500/20';
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <!-- Table Toolbar -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/20 px-4 py-3"
        >
            <div>
                <h4 class="text-sm font-bold text-foreground">
                    Auditoria Ponto a Ponto: {{ result.parameter_name }}
                </h4>
                <p class="text-xs text-muted-foreground">
                    Exibição comparativa lado a lado dos cálculos de EWMA e
                    CUSUM
                </p>
            </div>

            <div class="flex items-center gap-2">
                <!-- Alert filter toggle -->
                <div
                    class="inline-flex rounded-lg border bg-background p-0.5 text-xs"
                >
                    <button
                        type="button"
                        @click="
                            alertFilter = 'all';
                            currentPage = 1;
                        "
                        :class="[
                            'cursor-pointer rounded-md px-2.5 py-1 font-medium transition-colors',
                            alertFilter === 'all'
                                ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Todas ({{ result.rows.length }})
                    </button>
                    <button
                        type="button"
                        @click="
                            alertFilter = 'alerts_only';
                            currentPage = 1;
                        "
                        :class="[
                            'cursor-pointer rounded-md px-2.5 py-1 font-medium transition-colors',
                            alertFilter === 'alerts_only'
                                ? 'bg-amber-500 font-semibold text-white shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Com Alertas
                    </button>
                </div>

                <!-- Search -->
                <div class="relative flex items-center">
                    <Search
                        class="absolute left-2.5 h-3.5 w-3.5 text-muted-foreground"
                    />
                    <input
                        v-model="searchQuery"
                        placeholder="Buscar data/valor..."
                        class="h-8 w-44 rounded-md border border-input bg-background pr-2.5 pl-8 text-xs font-medium focus:ring-2 focus:ring-ring focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr
                        class="border-b bg-muted/40 font-semibold text-muted-foreground"
                    >
                        <th class="w-16 px-3 py-2.5">Ponto</th>
                        <th class="px-3 py-2.5">Data / Hora</th>
                        <th class="px-3 py-2.5 text-right">Medição (X)</th>

                        <!-- EWMA Group Header -->
                        <th
                            class="border-l bg-primary/5 px-3 py-2.5 text-center text-primary"
                        >
                            EWMA (Zᵢ)
                        </th>
                        <th
                            class="bg-primary/5 px-3 py-2.5 text-center text-primary"
                        >
                            Alerta EWMA
                        </th>
                        <th class="bg-primary/5 px-3 py-2.5 text-primary">
                            Próximo Limite & Margem
                        </th>

                        <!-- CUSUM Group Header -->
                        <th
                            class="border-l bg-amber-500/5 px-3 py-2.5 text-center text-amber-700 dark:text-amber-400"
                        >
                            CUSUM (C⁺ / C⁻)
                        </th>
                        <th
                            class="bg-amber-500/5 px-3 py-2.5 text-center text-amber-700 dark:text-amber-400"
                        >
                            Alerta CUSUM
                        </th>
                        <th
                            class="bg-amber-500/5 px-3 py-2.5 text-amber-700 dark:text-amber-400"
                        >
                            Próximo Patamar & Margem
                        </th>

                        <!-- Action -->
                        <th class="border-l px-3 py-2.5 text-center">
                            Memorial
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    <tr
                        v-for="row in paginatedRows"
                        :key="row.index"
                        class="transition-colors hover:bg-muted/30"
                    >
                        <!-- Ponto -->
                        <td
                            class="px-3 py-2 font-mono text-[11px] text-muted-foreground"
                        >
                            #{{ row.index }}
                        </td>

                        <!-- Data / Hora -->
                        <td class="px-3 py-2">
                            <span class="block font-medium text-foreground">{{
                                row.date
                            }}</span>
                            <span
                                class="font-mono text-[10px] text-muted-foreground"
                                >{{ row.time }}</span
                            >
                        </td>

                        <!-- Medição Real -->
                        <td
                            class="px-3 py-2 text-right font-mono font-bold text-foreground"
                        >
                            {{ row.raw_value.toFixed(result.decimals) }}
                        </td>

                        <!-- EWMA Z_i -->
                        <td
                            class="border-l bg-primary/5 px-3 py-2 text-center font-mono font-semibold text-primary"
                        >
                            {{ row.ewma.z_value.toFixed(result.decimals + 1) }}
                        </td>

                        <!-- EWMA Status -->
                        <td class="bg-primary/5 px-3 py-2 text-center">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                                    getAlertBadgeClass(row.ewma.alert_level),
                                ]"
                            >
                                <CheckCircle2
                                    v-if="row.ewma.alert_level === 0"
                                    class="h-3 w-3"
                                />
                                <AlertTriangle v-else class="h-3 w-3" />
                                <span>{{
                                    row.ewma.alert_level === 0
                                        ? 'Normal'
                                        : `Alerta ${row.ewma.alert_level}`
                                }}</span>
                            </span>
                        </td>

                        <!-- EWMA Closest Limit -->
                        <td
                            class="bg-primary/5 px-3 py-2 font-mono text-[11px]"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="text-muted-foreground"
                                    >{{
                                        row.ewma.closest_limit.target_name
                                    }}:</span
                                >
                                <span
                                    :class="[
                                        'font-semibold',
                                        row.ewma.closest_limit.margin < 0
                                            ? 'text-red-600'
                                            : 'text-foreground',
                                    ]"
                                >
                                    {{
                                        row.ewma.closest_limit.margin >= 0
                                            ? `+${row.ewma.closest_limit.margin.toFixed(3)}`
                                            : row.ewma.closest_limit.margin.toFixed(
                                                  3,
                                              )
                                    }}
                                </span>
                            </div>
                        </td>

                        <!-- CUSUM (C+ / C-) -->
                        <td
                            class="border-l bg-amber-500/5 px-3 py-2 text-center font-mono font-semibold"
                        >
                            <span class="text-sky-600">{{
                                row.cusum.c_pos.toFixed(2)
                            }}</span>
                            <span class="mx-1 text-muted-foreground">/</span>
                            <span class="text-orange-600">{{
                                row.cusum.c_neg.toFixed(2)
                            }}</span>
                        </td>

                        <!-- CUSUM Status -->
                        <td class="bg-amber-500/5 px-3 py-2 text-center">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                                    getAlertBadgeClass(row.cusum.alert_level),
                                ]"
                            >
                                <CheckCircle2
                                    v-if="row.cusum.alert_level === 0"
                                    class="h-3 w-3"
                                />
                                <AlertTriangle v-else class="h-3 w-3" />
                                <span>{{
                                    row.cusum.alert_level === 0
                                        ? 'Normal'
                                        : `Alerta ${row.cusum.alert_level}`
                                }}</span>
                            </span>
                        </td>

                        <!-- CUSUM Closest Threshold -->
                        <td
                            class="bg-amber-500/5 px-3 py-2 font-mono text-[11px]"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="text-muted-foreground"
                                    >{{
                                        row.cusum.closest_threshold.target_name
                                    }}:</span
                                >
                                <span
                                    :class="[
                                        'font-semibold',
                                        row.cusum.closest_threshold.margin < 0
                                            ? 'text-red-600'
                                            : 'text-foreground',
                                    ]"
                                >
                                    {{
                                        row.cusum.closest_threshold.margin >= 0
                                            ? `+${row.cusum.closest_threshold.margin.toFixed(3)}`
                                            : row.cusum.closest_threshold.margin.toFixed(
                                                  3,
                                              )
                                    }}
                                </span>
                            </div>
                        </td>

                        <!-- Memorial Action Button -->
                        <td class="border-l px-3 py-2 text-center">
                            <button
                                type="button"
                                @click="emit('openMemorial', row)"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border bg-muted/40 px-2 py-1 text-[11px] font-medium text-foreground shadow-2xs transition-all hover:bg-primary hover:text-primary-foreground"
                                title="Ver memorial de cálculo passo a passo"
                            >
                                <Calculator class="h-3 w-3" />
                                <span>Ver Fórmula</span>
                            </button>
                        </td>
                    </tr>

                    <tr v-if="paginatedRows.length === 0">
                        <td
                            colspan="10"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Nenhum ponto registrado no período selecionado ou
                            com os filtros ativos.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div
            class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-xs"
        >
            <span class="text-muted-foreground">
                Exibindo {{ paginatedRows.length }} de
                {{ filteredRows.length }} pontos
            </span>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    @click="currentPage = Math.max(1, currentPage - 1)"
                    :disabled="currentPage === 1"
                    class="rounded p-1 transition-colors hover:bg-muted disabled:opacity-30"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <span class="px-2 font-medium">
                    Página {{ currentPage }} de {{ totalPages }}
                </span>
                <button
                    type="button"
                    @click="currentPage = Math.min(totalPages, currentPage + 1)"
                    :disabled="currentPage === totalPages"
                    class="rounded p-1 transition-colors hover:bg-muted disabled:opacity-30"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
