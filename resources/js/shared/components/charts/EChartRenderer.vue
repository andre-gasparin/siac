<script setup lang="ts">
import * as echarts from 'echarts';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

export interface ChartSeries {
    parameter_id: number;
    label: string;
    chart_type: 'line' | 'bar' | 'area';
    color: string;
    stroke_width: number;
    axis_position: 'left' | 'right';
    min_val?: number | null;
    max_val?: number | null;
    unit?: string;
    show_points?: boolean;
    show_values?: boolean;
    data: Array<{ timestamp: string; formatted_time?: string; value: number }>;
}

const props = withDefaults(
    defineProps<{
        series: ChartSeries[];
        height?: number;
    }>(),
    { height: 300 },
);

const chartElement = ref<HTMLDivElement | null>(null);
let chart: echarts.ECharts | null = null;
let resizeObserver: ResizeObserver | null = null;

function renderChart() {
    if (!chartElement.value) {
        return;
    }

    chart ??= echarts.init(chartElement.value);
    const axes = new Map<string, number>();
    const yAxis: echarts.YAXisComponentOption[] = [];

    const renderedSeries = props.series.map((series) => {
        const key = series.axis_position ?? 'left';
        let axisIndex = axes.get(key);

        if (axisIndex === undefined) {
            axisIndex = yAxis.length;
            axes.set(key, axisIndex);
            yAxis.push({
                type: 'value',
                position: key,
                min: series.min_val ?? undefined,
                max: series.max_val ?? undefined,
                name: series.unit ?? '',
                splitLine: { show: axisIndex === 0 },
            });
        }

        const isBar = series.chart_type === 'bar';
        const isArea = series.chart_type === 'area';

        return {
            name: series.label,
            type: isBar ? ('bar' as const) : ('line' as const),
            yAxisIndex: axisIndex,
            data: series.data.map((point) => [point.timestamp, point.value]),
            showSymbol: series.show_points ?? true,
            symbol: 'circle',
            symbolSize: 6,
            label: {
                show: series.show_values ?? false,
                position: 'top' as const,
            },
            smooth: !isBar,
            lineStyle: { width: series.stroke_width ?? 2, color: series.color },
            itemStyle: { color: series.color },
            areaStyle: isArea
                ? { opacity: 0.2, color: series.color }
                : undefined,
        };
    });

    chart.setOption(
        {
            animation: false,
            tooltip: { trigger: 'axis' },
            legend: { type: 'scroll', top: 0 },
            grid: { left: 52, right: 52, top: 32, bottom: 58 },
            xAxis: { type: 'time' },
            yAxis: yAxis.length > 0 ? yAxis : [{ type: 'value' }],
            dataZoom: [
                { type: 'inside' },
                { type: 'slider', height: 18, bottom: 10 },
            ],
            series: renderedSeries,
        },
        true,
    );
}

watch(() => props.series, renderChart, { deep: true });

onMounted(() => {
    renderChart();
    resizeObserver = new ResizeObserver(() => chart?.resize());
    resizeObserver.observe(chartElement.value!);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    chart?.dispose();
});
</script>

<template>
    <div
        ref="chartElement"
        class="w-full"
        :style="{ height: `${height}px` }"
        data-report-chart-renderer
    />
</template>
