<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import type { DataRow, ParameterItem } from '@/features/data-table/types';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';
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
const series = computed<ChartSeries[]>(() => [
    {
        parameter_id: props.parameter.id,
        label: props.parameter.name,
        chart_type: chartType.value,
        color: '#059669',
        stroke_width: 2,
        axis_position: 'left',
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
            <DialogHeader
                ><DialogTitle
                    >{{ parameter.name }} ·
                    {{ parameter.system_name }}</DialogTitle
                ><DialogDescription
                    >{{ parameter.tag || parameter.code }} · Leituras do período
                    carregado</DialogDescription
                ></DialogHeader
            >
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
