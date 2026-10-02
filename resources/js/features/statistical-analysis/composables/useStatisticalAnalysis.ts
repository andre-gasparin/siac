import { computed, onScopeDispose, ref, watch } from 'vue';
import type {
    AnalysisResponse,
    AnalysisRow,
    ManualOverride,
    ParameterAnalysisResult,
    ParameterItem,
    SystemItem,
} from '@/features/statistical-analysis/types';
import { data as statisticalAnalysisData } from '@/routes/statistical-analysis';

export function useStatisticalAnalysis(
    getTeamSlug: () => string,
    systems: SystemItem[],
    defaultStartDate: string,
    defaultEndDate: string,
    defaultLookbackValue: number = 30,
    defaultLookbackUnit: 'days' | 'samples' = 'days',
    defaultFrequency: 'raw' | 'daily_avg' = 'raw',
) {
    const selectedSystemId = ref<number | null>(
        systems.length > 0 ? systems[0].id : null,
    );
    const selectedParameterIds = ref<number[]>([]);

    const startDate = ref<string>(defaultStartDate);
    const endDate = ref<string>(defaultEndDate);
    const lookbackValue = ref<number>(defaultLookbackValue);
    const lookbackUnit = ref<'days' | 'samples'>(defaultLookbackUnit);
    const frequency = ref<'raw' | 'daily_avg'>(defaultFrequency);

    const numAlertLevels = ref<number>(3);
    const ewmaLambda = ref<number>(0.2);
    const ewmaLevels = ref<number[]>([1.5, 2.0, 3.0]);
    const cusumK = ref<number>(0.5);
    const cusumLevels = ref<number[]>([3.5, 4.5, 5.5]);

    const manualOverrides = ref<Record<number, ManualOverride>>({});

    const isLoading = ref<boolean>(false);
    const hasLoaded = ref<boolean>(false);
    const errorMessage = ref<string | null>(null);

    const results = ref<Record<number, ParameterAnalysisResult>>({});
    const returnedParameters = ref<ParameterItem[]>([]);
    const activeParameterId = ref<number | null>(null);

    const inspectingRow = ref<AnalysisRow | null>(null);
    const isMemorialOpen = ref<boolean>(false);
    let requestId = 0;
    let requestController: AbortController | null = null;

    function cancelPendingRequest() {
        requestId++;
        requestController?.abort();
        requestController = null;
        isLoading.value = false;
    }

    onScopeDispose(cancelPendingRequest);

    // Current available parameters based on selected system
    const availableParameters = computed<ParameterItem[]>(() => {
        if (!selectedSystemId.value) {
            return [];
        }

        const found = systems.find((s) => s.id === selectedSystemId.value);

        return found?.parameters ?? [];
    });

    // Auto-select first parameter when system changes if none selected
    watch(
        [selectedSystemId, getTeamSlug],
        () => {
            cancelPendingRequest();

            if (availableParameters.value.length > 0) {
                // Select up to 3 parameters by default
                selectedParameterIds.value = availableParameters.value
                    .slice(0, Math.min(3, availableParameters.value.length))
                    .map((p) => p.id);
            } else {
                selectedParameterIds.value = [];
            }

            results.value = {};
            returnedParameters.value = [];
            hasLoaded.value = false;
            activeParameterId.value = null;
            errorMessage.value = null;
            closeMemorial();
        },
        { immediate: true, flush: 'sync' },
    );

    // Active result for chart and detailed table
    const activeResult = computed<ParameterAnalysisResult | null>(() => {
        if (!activeParameterId.value) {
            return null;
        }

        return results.value[activeParameterId.value] ?? null;
    });

    function openMemorial(row: AnalysisRow) {
        inspectingRow.value = row;
        isMemorialOpen.value = true;
    }

    function closeMemorial() {
        isMemorialOpen.value = false;
        inspectingRow.value = null;
    }

    async function loadData() {
        if (
            !selectedSystemId.value ||
            selectedParameterIds.value.length === 0
        ) {
            errorMessage.value =
                'Selecione ao menos um parâmetro para análise.';

            return;
        }

        cancelPendingRequest();
        const currentRequestId = requestId;
        const controller = new AbortController();
        requestController = controller;
        isLoading.value = true;
        errorMessage.value = null;

        try {
            const teamSlug = getTeamSlug();
            const queryOverrides: Record<
                string,
                { mean?: number | null; std_dev?: number | null }
            > = {};

            for (const [parameterId, override] of Object.entries(
                manualOverrides.value,
            )) {
                queryOverrides[parameterId] = { ...override };
            }

            const res = await fetch(
                statisticalAnalysisData.url(
                    { current_team: teamSlug },
                    {
                        query: {
                            system_id: selectedSystemId.value,
                            parameter_ids: selectedParameterIds.value,
                            start_date: startDate.value,
                            end_date: endDate.value,
                            lookback_value: lookbackValue.value,
                            lookback_unit: lookbackUnit.value,
                            frequency: frequency.value,
                            num_alert_levels: numAlertLevels.value,
                            ewma_lambda: ewmaLambda.value,
                            ewma_levels: ewmaLevels.value,
                            cusum_k: cusumK.value,
                            cusum_levels: cusumLevels.value,
                            manual_overrides: queryOverrides,
                        },
                    },
                ),
                {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!res.ok) {
                throw new Error(`Falha ao carregar dados: ${res.statusText}`);
            }

            const data: AnalysisResponse = await res.json();

            if (currentRequestId !== requestId || controller.signal.aborted) {
                return;
            }

            results.value = data.results || {};
            returnedParameters.value = data.parameters || [];
            hasLoaded.value = true;

            // Set active parameter to first with results if not already set
            const resultKeys = Object.keys(results.value).map(Number);

            if (resultKeys.length > 0) {
                if (
                    !activeParameterId.value ||
                    !results.value[activeParameterId.value]
                ) {
                    activeParameterId.value = resultKeys[0];
                }
            } else {
                activeParameterId.value = null;
            }
        } catch (err: unknown) {
            if (currentRequestId === requestId && !controller.signal.aborted) {
                errorMessage.value =
                    err instanceof Error
                        ? err.message
                        : 'Erro inesperado ao processar análise.';
            }
        } finally {
            if (currentRequestId === requestId) {
                requestController = null;
                isLoading.value = false;
            }
        }
    }

    return {
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
        manualOverrides,
        isLoading,
        hasLoaded,
        errorMessage,
        results,
        returnedParameters,
        activeParameterId,
        activeResult,
        availableParameters,
        inspectingRow,
        isMemorialOpen,
        openMemorial,
        closeMemorial,
        loadData,
    };
}
