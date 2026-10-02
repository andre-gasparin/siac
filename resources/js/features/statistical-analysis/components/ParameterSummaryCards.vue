<script setup lang="ts">
import { Activity, AlertTriangle, CheckCircle, TrendingUp } from '@lucide/vue';
import type { ParameterAnalysisResult } from '@/features/statistical-analysis/types';

defineProps<{
    results: Record<number, ParameterAnalysisResult>;
    activeParameterId: number | null;
}>();

const emit = defineEmits<{
    select: [parameterId: number];
}>();

function getTotalAlerts(res: ParameterAnalysisResult): number {
    const ewmaAlerts = Object.values(res.summary.ewma.alert_counts).reduce(
        (a, b) => a + b,
        0,
    );
    const cusumAlerts = Object.values(res.summary.cusum.alert_counts).reduce(
        (a, b) => a + b,
        0,
    );

    return ewmaAlerts + cusumAlerts;
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <h3
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Sumário dos Parâmetros Analisados
            </h3>
            <span class="text-xs text-muted-foreground">
                Clique no card para alternar a visualização detalhada
            </span>
        </div>

        <div
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="res in results"
                :key="res.parameter_id"
                @click="emit('select', res.parameter_id)"
                :class="[
                    'relative flex cursor-pointer flex-col justify-between rounded-xl border p-4 text-xs transition-all select-none',
                    activeParameterId === res.parameter_id
                        ? 'border-primary bg-primary/5 shadow-md ring-2 ring-primary/20'
                        : 'border-border bg-card shadow-xs hover:border-primary/50 hover:bg-muted/30',
                ]"
            >
                <!-- Card Header -->
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span
                            class="block truncate text-sm font-bold text-foreground"
                        >
                            {{ res.parameter_name }}
                        </span>
                        <span
                            class="font-mono text-[11px] text-muted-foreground"
                        >
                            Unidade: {{ res.unit || 's/u' }} •
                            {{ res.summary.total_analyzed }} medições
                        </span>
                    </div>

                    <!-- Health status badge -->
                    <span
                        v-if="getTotalAlerts(res) === 0"
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                    >
                        <CheckCircle class="h-3 w-3" />
                        Estável
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400"
                    >
                        <AlertTriangle class="h-3 w-3" />
                        {{ getTotalAlerts(res) }} Alertas
                    </span>
                </div>

                <!-- Baseline Row -->
                <div
                    class="my-3 grid grid-cols-2 gap-2 rounded-lg border border-border/50 bg-background/60 p-2 text-[11px]"
                >
                    <div>
                        <span class="block text-[10px] text-muted-foreground"
                            >Média Alvo (μ₀):</span
                        >
                        <span class="font-mono font-semibold text-foreground">
                            {{ res.baseline.effective_mu_0 }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-muted-foreground"
                            >Desvio Padrão (σ₀):</span
                        >
                        <span class="font-mono font-semibold text-foreground">
                            {{ res.baseline.effective_sigma_0 }}
                        </span>
                    </div>
                </div>

                <!-- EWMA & CUSUM Metrics -->
                <div
                    class="grid grid-cols-2 gap-3 border-t border-border/50 pt-2"
                >
                    <!-- EWMA Box -->
                    <div class="space-y-1">
                        <div
                            class="flex items-center justify-between text-[11px]"
                        >
                            <span
                                class="flex items-center gap-1 font-semibold text-primary"
                            >
                                <TrendingUp class="h-3 w-3" /> EWMA
                            </span>
                            <span class="font-mono font-bold text-foreground">
                                {{ res.summary.ewma.in_control_percentage }}%
                            </span>
                        </div>
                        <div
                            class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-1.5 rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${res.summary.ewma.in_control_percentage}%`,
                                }"
                            />
                        </div>
                        <span class="block text-[10px] text-muted-foreground">
                            Sob controle:
                            {{ res.summary.ewma.in_control_count }}/{{
                                res.summary.total_analyzed
                            }}
                        </span>
                    </div>

                    <!-- CUSUM Box -->
                    <div class="space-y-1">
                        <div
                            class="flex items-center justify-between text-[11px]"
                        >
                            <span
                                class="flex items-center gap-1 font-semibold text-amber-600 dark:text-amber-400"
                            >
                                <Activity class="h-3 w-3" /> CUSUM
                            </span>
                            <span class="font-mono font-bold text-foreground">
                                {{ res.summary.cusum.in_control_percentage }}%
                            </span>
                        </div>
                        <div
                            class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-1.5 rounded-full bg-amber-500 transition-all"
                                :style="{
                                    width: `${res.summary.cusum.in_control_percentage}%`,
                                }"
                            />
                        </div>
                        <span class="block text-[10px] text-muted-foreground">
                            Sob controle:
                            {{ res.summary.cusum.in_control_count }}/{{
                                res.summary.total_analyzed
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
