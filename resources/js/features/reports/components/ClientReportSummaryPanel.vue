<script setup lang="ts">
import { ArrowUpRight } from '@lucide/vue';
import {
    formatDateBr,
    formatPercentBr,
} from '@/features/reports/reportMetrics';
import type { SystemMetricsSummary } from '@/features/reports/reportMetrics';

export interface SummarySystemRow {
    id: number;
    name: string;
    hideData: boolean;
    metrics: SystemMetricsSummary;
}

defineProps<{
    dateReference: string;
    rows: SummarySystemRow[];
    totals: {
        total: number;
        eligible: number;
        deviations: number;
    };
}>();

const emit = defineEmits<{
    navigateSystem: [systemId: number];
}>();
</script>

<template>
    <div class="border-t border-border bg-card p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-bold text-foreground">
                Conformidade dos sistemas · {{ formatDateBr(dateReference) }}
            </h2>
            <span class="text-[11px] text-muted-foreground">
                Conformidade do dia
            </span>
        </div>

        <!-- 4 Métricas Gerais -->
        <div
            class="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-border/80 bg-muted/20 p-3 sm:grid-cols-4"
        >
            <div class="border-border/70 pr-2 sm:border-r">
                <span
                    class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    Total de dados
                </span>
                <strong class="mt-0.5 block text-xl font-bold text-foreground">
                    {{ totals.total || 'Sem dados' }}
                </strong>
            </div>

            <div class="pr-2 sm:border-r sm:border-border/70">
                <span
                    class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    Dados avaliáveis
                </span>
                <strong class="mt-0.5 block text-xl font-bold text-foreground">
                    {{ totals.eligible }}
                </strong>
            </div>

            <div class="border-border/70 pr-2 sm:border-r">
                <span
                    class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    Desvios
                </span>
                <strong
                    class="mt-0.5 block text-xl font-bold"
                    :class="
                        totals.deviations > 0
                            ? 'text-red-600 dark:text-red-400'
                            : 'text-foreground'
                    "
                >
                    {{ totals.eligible ? totals.deviations : '—' }}
                </strong>
            </div>

            <div>
                <span
                    class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    Conformidade
                </span>
                <strong class="mt-0.5 block text-xl font-bold text-primary">
                    {{
                        formatPercentBr(
                            totals.eligible - totals.deviations,
                            totals.eligible,
                        )
                    }}
                </strong>
            </div>
        </div>

        <!-- Tabela Detalhada por Sistema -->
        <div class="overflow-x-auto rounded-xl border border-border">
            <table
                class="w-full min-w-[560px] border-collapse text-xs tabular-nums"
            >
                <thead>
                    <tr
                        class="border-b border-border bg-muted text-center text-[11px] font-semibold text-muted-foreground"
                    >
                        <th class="px-3 py-2 text-left">Sistema</th>
                        <th class="px-3 py-2">Dados</th>
                        <th class="px-3 py-2">Avaliáveis</th>
                        <th class="px-3 py-2">Desvios</th>
                        <th class="px-3 py-2">% desvios</th>
                        <th class="w-36 px-3 py-2">Conformidade</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="hover:bg-[var(--report-hover)]"
                    >
                        <td class="px-3 py-2 text-left">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                @click="emit('navigateSystem', row.id)"
                            >
                                <span>{{ row.name }}</span>
                                <ArrowUpRight class="size-3" />
                            </button>
                        </td>
                        <td class="px-3 py-2 text-center">
                            {{
                                row.hideData
                                    ? 'Oculta'
                                    : row.metrics.total || 'Sem dados'
                            }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            {{ row.hideData ? '—' : row.metrics.eligible }}
                        </td>
                        <td
                            class="px-3 py-2 text-center"
                            :class="
                                row.metrics.deviations > 0
                                    ? 'font-semibold text-[#c52c32] dark:text-[#ff8588]'
                                    : ''
                            "
                        >
                            {{
                                row.hideData || !row.metrics.eligible
                                    ? '—'
                                    : row.metrics.deviations
                            }}
                        </td>
                        <td
                            class="px-3 py-2 text-center"
                            :class="
                                row.metrics.deviations > 0
                                    ? 'font-semibold text-[#c52c32] dark:text-[#ff8588]'
                                    : ''
                            "
                        >
                            {{ row.hideData ? '—' : row.metrics.deviationPct }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            <strong class="block">
                                {{
                                    row.hideData
                                        ? '—'
                                        : row.metrics.conformityPct
                                }}
                            </strong>
                            <div
                                v-if="!row.hideData && row.metrics.eligible > 0"
                                class="mt-1 flex h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <span
                                    class="bg-[#83b7a1] dark:bg-[#70bb98]"
                                    :style="{
                                        width: `${row.metrics.conformityRatio}%`,
                                    }"
                                />
                                <span
                                    class="bg-[#d95759] dark:bg-[#e66b70]"
                                    :style="{
                                        width: `${100 - row.metrics.conformityRatio}%`,
                                    }"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot
                    class="border-t border-border bg-muted/50 text-center font-bold"
                >
                    <tr>
                        <td class="px-3 py-2 text-left">Total</td>
                        <td class="px-3 py-2">
                            {{ totals.total || 'Sem dados' }}
                        </td>
                        <td class="px-3 py-2">{{ totals.eligible }}</td>
                        <td
                            class="px-3 py-2"
                            :class="
                                totals.deviations > 0
                                    ? 'text-red-600 dark:text-red-400'
                                    : ''
                            "
                        >
                            {{ totals.eligible ? totals.deviations : '—' }}
                        </td>
                        <td
                            class="px-3 py-2"
                            :class="
                                totals.deviations > 0
                                    ? 'text-red-600 dark:text-red-400'
                                    : ''
                            "
                        >
                            {{
                                formatPercentBr(
                                    totals.deviations,
                                    totals.eligible,
                                )
                            }}
                        </td>
                        <td class="px-3 py-2 text-primary">
                            {{
                                formatPercentBr(
                                    totals.eligible - totals.deviations,
                                    totals.eligible,
                                )
                            }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="mt-2.5 text-[11px] text-muted-foreground">
            Avaliáveis = resultados individuais válidos com limites de controle.
            Ausências e médias não integram o cálculo. Indicadores independentes
            da visualização.
        </p>
    </div>
</template>
