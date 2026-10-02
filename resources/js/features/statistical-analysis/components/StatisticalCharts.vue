<script setup lang="ts">
import * as echarts from 'echarts';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { ParameterAnalysisResult } from '@/features/statistical-analysis/types';

const props = defineProps<{
    result: ParameterAnalysisResult;
}>();

const ewmaChartRef = ref<HTMLDivElement | null>(null);
const cusumChartRef = ref<HTMLDivElement | null>(null);

let ewmaChart: echarts.ECharts | null = null;
let cusumChart: echarts.ECharts | null = null;
let resizeObserver: ResizeObserver | null = null;

function renderCharts() {
    renderEwma();
    renderCusum();
}

function renderEwma() {
    if (!ewmaChartRef.value) {
        return;
    }

    ewmaChart ??= echarts.init(ewmaChartRef.value);

    const chartData = props.result.chart_data;
    const timestamps = chartData.timestamps;
    const rawValues = chartData.raw_values;
    const zValues = chartData.ewma.z_values;
    const centerLine = chartData.ewma.center_line;
    const uclSeries = chartData.ewma.ucl_series;
    const lclSeries = chartData.ewma.lcl_series;

    const seriesList: echarts.SeriesOption[] = [
        // Raw Measurements
        {
            name: 'Medi\u00e7\u00e3o Real (X)',
            type: 'line',
            data: rawValues,
            smooth: false,
            showSymbol: true,
            symbol: 'circle',
            symbolSize: 4,
            lineStyle: { width: 1, color: '#94a3b8', type: 'dotted' },
            itemStyle: { color: '#94a3b8' },
        },
        // EWMA Z values
        {
            name: 'EWMA (Z)',
            type: 'line',
            data: zValues,
            smooth: true,
            showSymbol: true,
            symbol: 'circle',
            symbolSize: 6,
            lineStyle: { width: 2.5, color: '#3b82f6' },
            itemStyle: {
                color: (params: any) => {
                    const row = props.result.rows[params.dataIndex];

                    if (row?.ewma?.alert_level >= 3) {
                        return '#ef4444';
                    }

                    if (row?.ewma?.alert_level === 2) {
                        return '#f97316';
                    }

                    if (row?.ewma?.alert_level === 1) {
                        return '#f59e0b';
                    }

                    return '#3b82f6';
                },
            },
            markLine: {
                silent: true,
                symbol: ['none', 'none'],
                data: [
                    {
                        yAxis: centerLine,
                        lineStyle: {
                            color: '#10b981',
                            width: 1.5,
                            type: 'dashed',
                        },
                        label: {
                            formatter: `CL: ${centerLine.toFixed(2)}`,
                            position: 'end',
                        },
                    },
                ],
            },
        },
    ];

    // Add UCL & LCL lines for each level
    const colors = ['#f59e0b', '#f97316', '#ef4444'];
    Object.keys(uclSeries).forEach((lvlStr) => {
        const lvl = Number(lvlStr);
        const color = colors[lvl - 1] || '#ef4444';
        const uclData = uclSeries[lvl];
        const lclData = lclSeries[lvl];

        seriesList.push({
            name: `UCL ${lvl}`,
            type: 'line',
            data: uclData,
            smooth: true,
            showSymbol: false,
            lineStyle: { width: 1.5, color: color, type: 'dashed' },
            itemStyle: { color: color },
        });

        seriesList.push({
            name: `LCL ${lvl}`,
            type: 'line',
            data: lclData,
            smooth: true,
            showSymbol: false,
            lineStyle: { width: 1.5, color: color, type: 'dashed' },
            itemStyle: { color: color },
        });
    });

    ewmaChart.setOption(
        {
            tooltip: {
                trigger: 'axis',
                valueFormatter: (val: any) =>
                    typeof val === 'number' ? val.toFixed(3) : val,
            },
            legend: {
                type: 'scroll',
                top: 4,
                left: 10,
                right: 10,
                textStyle: { fontSize: 11 },
            },
            grid: {
                left: 55,
                right: 35,
                top: 38,
                bottom: 45,
                containLabel: true,
            },
            xAxis: {
                type: 'category',
                data: timestamps,
                axisLabel: {
                    formatter: (val: string) => {
                        const parts = val.split(' ');

                        return parts[0] || val;
                    },
                    fontSize: 10,
                },
            },
            yAxis: {
                type: 'value',
                scale: true,
                splitLine: { lineStyle: { type: 'dashed', opacity: 0.3 } },
                axisLabel: { fontSize: 10 },
            },
            dataZoom: [
                { type: 'inside' },
                { type: 'slider', height: 16, bottom: 5 },
            ],
            series: seriesList,
        },
        true,
    );
}

function renderCusum() {
    if (!cusumChartRef.value) {
        return;
    }

    cusumChart ??= echarts.init(cusumChartRef.value);

    const chartData = props.result.chart_data;
    const timestamps = chartData.timestamps;
    const cPosValues = chartData.cusum.c_pos_values;
    const cNegValues = chartData.cusum.c_neg_values;
    const hThresholds = chartData.cusum.h_thresholds;

    const seriesList: echarts.SeriesOption[] = [
        {
            name: 'CUSUM+ (Desvio Positivo)',
            type: 'line',
            data: cPosValues,
            smooth: true,
            showSymbol: true,
            symbol: 'circle',
            symbolSize: 5,
            lineStyle: { width: 2, color: '#0284c7' },
            itemStyle: {
                color: (params: any) => {
                    const row = props.result.rows[params.dataIndex];

                    if (
                        row?.cusum?.violated_side === 'pos' ||
                        row?.cusum?.violated_side === 'both'
                    ) {
                        return '#ef4444';
                    }

                    return '#0284c7';
                },
            },
        },
        {
            name: 'CUSUM- (Desvio Negativo)',
            type: 'line',
            data: cNegValues,
            smooth: true,
            showSymbol: true,
            symbol: 'circle',
            symbolSize: 5,
            lineStyle: { width: 2, color: '#ea580c' },
            itemStyle: {
                color: (params: any) => {
                    const row = props.result.rows[params.dataIndex];

                    if (
                        row?.cusum?.violated_side === 'neg' ||
                        row?.cusum?.violated_side === 'both'
                    ) {
                        return '#ef4444';
                    }

                    return '#ea580c';
                },
            },
        },
    ];

    // Mark lines for H thresholds
    const markLines: any[] = [];
    const colors = ['#f59e0b', '#f97316', '#ef4444'];

    hThresholds.forEach((hVal, idx) => {
        const lvl = idx + 1;
        const color = colors[idx] || '#ef4444';
        markLines.push({
            yAxis: hVal,
            lineStyle: { color: color, width: 1.5, type: 'dashed' },
            label: {
                formatter: `H${lvl}: ${hVal.toFixed(2)}`,
                position: 'end',
            },
        });
    });

    if (seriesList[0]) {
        seriesList[0].markLine = {
            silent: true,
            symbol: ['none', 'none'],
            data: markLines,
        };
    }

    cusumChart.setOption(
        {
            tooltip: {
                trigger: 'axis',
                valueFormatter: (val: any) =>
                    typeof val === 'number' ? val.toFixed(3) : val,
            },
            legend: {
                type: 'scroll',
                top: 4,
                left: 10,
                right: 10,
                textStyle: { fontSize: 11 },
            },
            grid: {
                left: 55,
                right: 35,
                top: 38,
                bottom: 45,
                containLabel: true,
            },
            xAxis: {
                type: 'category',
                data: timestamps,
                axisLabel: {
                    formatter: (val: string) => {
                        const parts = val.split(' ');

                        return parts[0] || val;
                    },
                    fontSize: 10,
                },
            },
            yAxis: {
                type: 'value',
                min: 0,
                splitLine: { lineStyle: { type: 'dashed', opacity: 0.3 } },
                axisLabel: { fontSize: 10 },
            },
            dataZoom: [
                { type: 'inside' },
                { type: 'slider', height: 16, bottom: 5 },
            ],
            series: seriesList,
        },
        true,
    );
}

function handleResize() {
    ewmaChart?.resize();
    cusumChart?.resize();
}

watch(() => props.result, renderCharts, { deep: true });

onMounted(() => {
    renderCharts();

    if (ewmaChartRef.value) {
        resizeObserver = new ResizeObserver(handleResize);
        resizeObserver.observe(ewmaChartRef.value);
    }
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    ewmaChart?.dispose();
    cusumChart?.dispose();
});
</script>

<template>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- EWMA Chart Card -->
        <div class="rounded-xl border bg-card p-4 shadow-xs">
            <div class="mb-2 flex items-center justify-between border-b pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    <h4 class="text-sm font-bold text-foreground">
                        Gráfico EWMA (Controle Estatístico Suavizado)
                    </h4>
                </div>
            </div>
            <div ref="ewmaChartRef" class="h-80 w-full" />
        </div>

        <!-- CUSUM Chart Card -->
        <div class="rounded-xl border bg-card p-4 shadow-xs">
            <div class="mb-2 flex items-center justify-between border-b pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                    <h4 class="text-sm font-bold text-foreground">
                        Gráfico CUSUM (Soma Acumulada Tabular)
                    </h4>
                </div>
            </div>
            <div ref="cusumChartRef" class="h-80 w-full" />
        </div>
    </div>
</template>
