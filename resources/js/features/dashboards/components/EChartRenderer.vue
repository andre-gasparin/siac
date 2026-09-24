<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';

interface SeriesConfig {
    parameter_id: number;
    label: string;
    chart_type: 'line' | 'bar' | 'area';
    color: string;
    stroke_width: number;
    independent_axis: boolean;
    axis_position: 'left' | 'right';
    min_val: number | null;
    max_val: number | null;
    unit?: string;
    data: Array<{ timestamp: string; formatted_time: string; value: number }>;
}

const props = defineProps<{
    series: SeriesConfig[];
}>();

const chartRef = ref<HTMLDivElement | null>(null);
let chartInstance: any = null;
let resizeObserver: ResizeObserver | null = null;

function loadEChartsScript(): Promise<void> {
    return new Promise((resolve, reject) => {
        if ((window as any).echarts) {
            resolve();

            return;
        }

        const existingScript = document.getElementById('echarts-cdn-script');

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve());
            existingScript.addEventListener('error', (e) => reject(e));

            return;
        }

        const script = document.createElement('script');
        script.id = 'echarts-cdn-script';
        script.src =
            'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js';
        script.async = true;
        script.onload = () => resolve();
        script.onerror = (e) => reject(e);
        document.head.appendChild(script);
    });
}

function initChart() {
    if (!chartRef.value || !(window as any).echarts) {
        return;
    }

    if (!chartInstance) {
        chartInstance = (window as any).echarts.init(chartRef.value);
    }

    // Build ECharts Option
    const legendData: string[] = [];
    const yAxisList: any[] = [];
    const seriesList: any[] = [];

    // Map axes index by position / independence
    const axisMap = new Map<string, number>();

    props.series.forEach((s) => {
        legendData.push(s.label);

        let axisIndex = 0;
        const axisKey = s.independent_axis
            ? `indep_${s.parameter_id}`
            : `shared_${s.axis_position || 'left'}`;

        if (!axisMap.has(axisKey)) {
            axisIndex = yAxisList.length;
            axisMap.set(axisKey, axisIndex);

            const isRight = (s.axis_position || 'left') === 'right';

            yAxisList.push({
                type: 'value',
                name: s.unit || '',
                nameLocation: 'middle',
                nameRotate: isRight ? 270 : 90,
                nameGap: 40,
                nameTextStyle: {
                    fontSize: 11,
                    fontWeight: 500,
                },
                position: s.axis_position || 'left',
                min:
                    s.min_val !== null && s.min_val !== undefined
                        ? s.min_val
                        : undefined,
                max:
                    s.max_val !== null && s.max_val !== undefined
                        ? s.max_val
                        : undefined,
                splitLine: {
                    show: axisIndex === 0,
                    lineStyle: { type: 'dashed', color: '#334155' },
                },
                axisLine: { show: true },
            });
        } else {
            axisIndex = axisMap.get(axisKey)!;
        }

        const chartDataPoints = (s.data || []).map((pt) => [
            pt.timestamp,
            pt.value,
        ]);

        const isArea = s.chart_type === 'area';
        const isBar = s.chart_type === 'bar';

        seriesList.push({
            name: s.label,
            type: isBar ? 'bar' : 'line',
            yAxisIndex: axisIndex,
            smooth: true,
            symbol: 'circle',
            symbolSize: 4,
            lineStyle: {
                width: s.stroke_width || 2,
                color: s.color || '#3B82F6',
            },
            itemStyle: {
                color: s.color || '#3B82F6',
            },
            areaStyle: isArea
                ? {
                      color: (window as any).echarts.graphic
                          ? new (window as any).echarts.graphic.LinearGradient(
                                0,
                                0,
                                0,
                                1,
                                [
                                    { offset: 0, color: s.color || '#3B82F6' },
                                    { offset: 1, color: 'transparent' },
                                ],
                            )
                          : s.color,
                      opacity: 0.25,
                  }
                : undefined,
            data: chartDataPoints,
        });
    });

    // Ensure at least one Y axis
    if (yAxisList.length === 0) {
        yAxisList.push({ type: 'value' });
    }

    const option = {
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'cross' },
        },

        legend: {
            type: 'scroll',
            data: legendData,
            top: 0,
            left: 0,
            right: 0,
            pageIconSize: 10,
            textStyle: { fontSize: 11 },
        },

        // Enable toolbox offscreen so dataZoomSelect and takeGlobalCursor work
        toolbox: {
            show: true,
            top: -9999,
            left: -9999,
            feature: {
                dataZoom: { yAxisIndex: 'none' },
                restore: {},
            },
        },

        grid: {
            left: '4%',
            right: '4%',
            top: 35,
            bottom: 44,
            containLabel: true,
        },

        // ECharts Minimap (Slider) at the bottom & Mouse Zoom (Inside)
        dataZoom: [
            {
                type: 'slider',
                show: true,
                xAxisIndex: [0],
                bottom: 2,
                height: 18,
                borderColor: 'transparent',
                backgroundColor: '#F1F5F9',
                fillerColor: 'rgba(59, 130, 246, 0.2)',
                handleStyle: { color: '#3B82F6' },
            },
            {
                type: 'inside',
                xAxisIndex: [0],
                zoomOnMouseWheel: true,
                moveOnMouseMove: true,
            },
        ],

        xAxis: {
            type: 'time',
            boundaryGap: false,
            axisLine: { lineStyle: { color: '#94A3B8' } },
        },
        yAxis: yAxisList,
        series: seriesList,
    };

    chartInstance.setOption(option, true);

    chartInstance.off('dataZoom');
    chartInstance.on('dataZoom', () => {
        if (isBoxZoomActive.value) {
            isBoxZoomActive.value = false;
        }
    });
}

const isBoxZoomActive = ref(false);

function toggleBoxZoom() {
    if (!chartInstance) {
        return;
    }

    isBoxZoomActive.value = !isBoxZoomActive.value;
    chartInstance.dispatchAction({
        type: 'takeGlobalCursor',
        key: 'dataZoomSelect',
        dataZoomSelectActive: isBoxZoomActive.value,
    });
}

function zoomIn() {
    if (!chartInstance) {
        return;
    }

    const option = chartInstance.getOption();
    const dz = option.dataZoom?.[0] || { start: 0, end: 100 };
    const currentStart = dz.start ?? 0;
    const currentEnd = dz.end ?? 100;
    const range = currentEnd - currentStart;
    const newRange = Math.max(5, range * 0.7);
    const center = (currentStart + currentEnd) / 2;
    let start = center - newRange / 2;
    let end = center + newRange / 2;

    if (start < 0) {
        start = 0;
        end = Math.min(100, newRange);
    }

    if (end > 100) {
        end = 100;
        start = Math.max(0, 100 - newRange);
    }

    chartInstance.dispatchAction({
        type: 'dataZoom',
        start,
        end,
    });
}

function zoomOut() {
    if (!chartInstance) {
        return;
    }

    const option = chartInstance.getOption();
    const dz = option.dataZoom?.[0] || { start: 0, end: 100 };
    const currentStart = dz.start ?? 0;
    const currentEnd = dz.end ?? 100;
    const range = currentEnd - currentStart;
    const newRange = Math.min(100, range * 1.4);
    const center = (currentStart + currentEnd) / 2;
    let start = center - newRange / 2;
    let end = center + newRange / 2;

    if (start < 0) {
        start = 0;
        end = Math.min(100, newRange);
    }

    if (end > 100) {
        end = 100;
        start = Math.max(0, 100 - newRange);
    }

    chartInstance.dispatchAction({
        type: 'dataZoom',
        start,
        end,
    });
}

function resetZoom() {
    if (!chartInstance) {
        return;
    }

    isBoxZoomActive.value = false;
    chartInstance.dispatchAction({
        type: 'dataZoom',
        start: 0,
        end: 100,
    });
    chartInstance.dispatchAction({
        type: 'restore',
    });
}

defineExpose({
    toggleBoxZoom,
    zoomIn,
    zoomOut,
    resetZoom,
    isBoxZoomActive,
});

watch(
    () => props.series,
    () => {
        if (chartInstance) {
            initChart();
        }
    },
    { deep: true },
);

onMounted(async () => {
    try {
        await loadEChartsScript();
        initChart();

        if (chartRef.value && typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => {
                if (chartInstance) {
                    chartInstance.resize();
                }
            });
            resizeObserver.observe(chartRef.value);
        }
    } catch (e) {
        console.error('Falha ao carregar Apache ECharts:', e);
    }
});

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
    }

    if (chartInstance) {
        chartInstance.dispose();
        chartInstance = null;
    }
});
</script>

<template>
    <div class="relative h-full min-h-[160px] w-full">
        <div ref="chartRef" class="h-full min-h-[160px] w-full"></div>
    </div>
</template>
