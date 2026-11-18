<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import type { DataRow, ParameterItem } from '@/features/data-table/types';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';
import ParameterCategoryBadge from '@/shared/components/ParameterCategoryBadge.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/shared/components/ui/dialog';

const EChartRenderer = defineAsyncComponent(
    () => import('@/shared/components/charts/EChartRenderer.vue'),
);
const props = defineProps<{
    parameter: ParameterItem;
    rows: DataRow[];
    type: 'line' | 'bar';
}>();
const emit = defineEmits<{ close: [] }>();
const chartType = ref(props.type);

function formatLimitValue(value: number, decimals: number): string {
    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

const hasLimits = computed(
    () =>
        props.parameter.alert_1_min != null ||
        props.parameter.alert_1_max != null,
);

const series = computed<ChartSeries[]>(() => [
    {
        parameter_id: props.parameter.id,
        label: props.parameter.name,
        chart_type: chartType.value,
        color: '#059669',
        stroke_width: 2,
        axis_position: 'left',
        min_val: props.parameter.alert_1_min,
        max_val: props.parameter.alert_1_max,
        decimals: props.parameter.decimals,
        unit: props.parameter.unit,
        data: props.rows.flatMap((row) =>
            row.values[props.parameter.id] == null
                ? []
                : [
                      {
                          timestamp: row.timestamp.replace(' ', 'T'),
                          value: row.values[props.parameter.id],
                      },
                  ],
        ),
    },
]);
</script>

<template>
    <Dialog :open="true" @update:open="!$event && emit('close')">
        <DialogContent class="sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle class="flex flex-wrap items-center gap-2">
                    <span>
                        {{ parameter.name }} · {{ parameter.system_name }}
                    </span>
                    <ParameterCategoryBadge
                        v-if="parameter.category"
                        :category="parameter.category"
                    />
                </DialogTitle>
                <DialogDescription>
                    {{
                        parameter.tag || parameter.code
                            ? `TAG: ${parameter.tag || parameter.code} · `
                            : ''
                    }}Leituras do período carregado
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex gap-2">
                    <button
                        v-for="kind in ['line', 'bar'] as const"
                        :key="kind"
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        :class="
                            chartType === kind &&
                            'bg-primary text-primary-foreground'
                        "
                        @click="chartType = kind"
                    >
                        {{ kind === 'line' ? 'Linha' : 'Barras' }}
                    </button>
                </div>
                <div
                    v-if="hasLimits"
                    class="flex flex-wrap items-center gap-2 text-xs tabular-nums"
                    data-test="chart-dialog-limits"
                >
                    <span
                        v-if="parameter.alert_1_min != null"
                        class="inline-flex items-center gap-1 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 font-medium text-red-700 dark:text-red-300"
                    >
                        Mínimo:
                        {{
                            formatLimitValue(
                                parameter.alert_1_min,
                                parameter.decimals,
                            )
                        }}{{ parameter.unit ? ` ${parameter.unit}` : '' }}
                    </span>
                    <span
                        v-if="parameter.alert_1_max != null"
                        class="inline-flex items-center gap-1 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 font-medium text-red-700 dark:text-red-300"
                    >
                        Máximo:
                        {{
                            formatLimitValue(
                                parameter.alert_1_max,
                                parameter.decimals,
                            )
                        }}{{ parameter.unit ? ` ${parameter.unit}` : '' }}
                    </span>
                </div>
            </div>
            <EChartRenderer
                v-if="series[0].data.length"
                :series="series"
                :height="360"
            />
            <p v-else class="py-12 text-center text-muted-foreground">
                Sem leituras para este parâmetro no período.
            </p>
        </DialogContent>
    </Dialog>
</template>
