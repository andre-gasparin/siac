<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Activity, AlertCircle, LineChart } from '@lucide/vue';
import { ref } from 'vue';
import CalculationMemorialModal from '@/features/statistical-analysis/components/CalculationMemorialModal.vue';
import ParameterSummaryCards from '@/features/statistical-analysis/components/ParameterSummaryCards.vue';
import StatisticalAuditTable from '@/features/statistical-analysis/components/StatisticalAuditTable.vue';
import StatisticalCharts from '@/features/statistical-analysis/components/StatisticalCharts.vue';
import StatisticalFactorDialog from '@/features/statistical-analysis/components/StatisticalFactorDialog.vue';
import StatisticalFilterBar from '@/features/statistical-analysis/components/StatisticalFilterBar.vue';
import { useStatisticalAnalysis } from '@/features/statistical-analysis/composables/useStatisticalAnalysis';
import type { SystemItem } from '@/features/statistical-analysis/types';
import { index as statisticalAnalysisIndex } from '@/routes/statistical-analysis';
import type { Team } from '@/shared/types';

const props = defineProps<{
    systems: SystemItem[];
    currentTeam: Team;
    defaultStartDate: string;
    defaultEndDate: string;
    defaultLookbackValue?: number;
    defaultLookbackUnit?: 'days' | 'samples';
    defaultFrequency?: 'raw' | 'daily_avg';
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Controle Estatístico',
            href: props.currentTeam
                ? statisticalAnalysisIndex.url({
                      current_team: props.currentTeam.slug,
                  })
                : '/',
        },
    ],
});

const isFactorDialogOpen = ref(false);

const {
    selectedSystemId,
    selectedParameterIds,
    startDate,
    endDate,
    lookbackValue,
    lookbackUnit,
    frequency,
    numAlertLevels,
    ewmaLambda,
    ewmaLevels,
    cusumK,
    cusumLevels,
    isLoading,
    hasLoaded,
    errorMessage,
    results,
    activeParameterId,
    activeResult,
    availableParameters,
    inspectingRow,
    isMemorialOpen,
    openMemorial,
    loadData,
} = useStatisticalAnalysis(
    () => props.currentTeam.slug,
    props.systems,
    props.defaultStartDate,
    props.defaultEndDate,
    props.defaultLookbackValue ?? 30,
    props.defaultLookbackUnit ?? 'days',
    props.defaultFrequency ?? 'raw',
);
</script>

<template>
    <Head title="Controle Estatístico (EWMA & CUSUM)" />

    <div class="space-y-5 p-4 sm:p-6">
        <!-- Page Title & Header -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-xl font-bold tracking-tight text-foreground"
                >
                    <Activity class="h-6 w-6 text-primary" />
                    <span>Controle Estatístico de Processos</span>
                </h1>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Análise comparativa avançada com Média Móvel Ponderada
                    Exponencialmente (EWMA) e Soma Acumulada Tabular (CUSUM).
                </p>
            </div>
        </div>

        <!-- Filter Bar -->
        <StatisticalFilterBar
            v-model:selectedSystemId="selectedSystemId"
            v-model:selectedParameterIds="selectedParameterIds"
            v-model:startDate="startDate"
            v-model:endDate="endDate"
            v-model:lookbackValue="lookbackValue"
            v-model:lookbackUnit="lookbackUnit"
            v-model:frequency="frequency"
            :systems="systems"
            :availableParameters="availableParameters"
            :isLoading="isLoading"
            @load="loadData"
            @openFactors="isFactorDialogOpen = true"
        />

        <!-- Error Alert -->
        <div
            v-if="errorMessage"
            class="flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 p-3.5 text-xs text-red-600 dark:text-red-400"
        >
            <AlertCircle class="h-4 w-4 shrink-0" />
            <span>{{ errorMessage }}</span>
        </div>

        <!-- Main Content Area when loaded -->
        <div
            v-if="hasLoaded && Object.keys(results).length > 0"
            class="space-y-6"
        >
            <!-- Parameter Cards Summary -->
            <ParameterSummaryCards
                :results="results"
                :activeParameterId="activeParameterId"
                @select="activeParameterId = $event"
            />

            <!-- Active Parameter Detailed View -->
            <div v-if="activeResult" class="space-y-4 pt-2">
                <!-- Detailed Section Header -->
                <div class="flex items-center justify-between border-b pb-2">
                    <div>
                        <h2
                            class="flex items-center gap-2 text-base font-bold text-foreground"
                        >
                            <span
                                >Visualização Detalhada:
                                {{ activeResult.parameter_name }}</span
                            >
                            <span
                                v-if="activeResult.unit"
                                class="text-xs font-normal text-muted-foreground"
                            >
                                ({{ activeResult.unit }})
                            </span>
                        </h2>
                        <span class="text-xs text-muted-foreground">
                            Linha de Base com
                            {{ activeResult.baseline.count }} registros prévios
                            • μ₀ = {{ activeResult.baseline.effective_mu_0 }} •
                            σ₀ = {{ activeResult.baseline.effective_sigma_0 }}
                        </span>
                    </div>
                </div>

                <!-- Side-by-side Audit Table -->
                <StatisticalAuditTable
                    :result="activeResult"
                    @openMemorial="openMemorial"
                />

                <!-- Charts (Abaixo da Tabela) -->
                <StatisticalCharts :result="activeResult" />
            </div>
        </div>

        <!-- Empty / Call-to-action State -->
        <div
            v-else-if="!isLoading"
            class="flex flex-col items-center justify-center rounded-2xl border border-dashed bg-card/40 p-12 text-center"
        >
            <div
                class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary"
            >
                <LineChart class="h-7 w-7" />
            </div>
            <h3 class="text-sm font-semibold text-foreground">
                Pronto para Iniciar o Controle Estatístico
            </h3>
            <p class="mt-1 mb-4 max-w-md text-xs text-muted-foreground">
                Selecione o sistema, os parâmetros que deseja auditar, configure
                o período e clique em
                <strong>"Processar Análise"</strong> para calcular as curvas de
                EWMA, limites exatos e CUSUM.
            </p>
            <button
                type="button"
                @click="loadData"
                :disabled="selectedParameterIds.length === 0"
                class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg bg-primary px-4 text-xs font-semibold text-primary-foreground shadow transition-colors hover:bg-primary/90 disabled:opacity-50"
            >
                <Activity class="h-4 w-4" />
                <span>Calcular Agora</span>
            </button>
        </div>

        <!-- Modals & Dialogs -->
        <StatisticalFactorDialog
            v-model:isOpen="isFactorDialogOpen"
            v-model:numAlertLevels="numAlertLevels"
            v-model:ewmaLambda="ewmaLambda"
            v-model:ewmaLevels="ewmaLevels"
            v-model:cusumK="cusumK"
            v-model:cusumLevels="cusumLevels"
        />

        <CalculationMemorialModal
            v-model:isOpen="isMemorialOpen"
            :row="inspectingRow"
        />
    </div>
</template>
