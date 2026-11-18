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
    decimals?: number;
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

function formatAxisDate(value: number | string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');

    return `${day}/${month}`;
}

function formatTooltipDateTime(value: number | string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day}/${month}/${year} ${hours}:${minutes}`;
}

function formatNumericValue(value: number, decimals?: number): string {
    if (!Number.isFinite(value)) {
        return '';
    }

    if (decimals !== undefined && Number.isFinite(decimals)) {
        return value.toLocaleString('pt-BR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    return value.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
}

function toFiniteNumber(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numeric = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(numeric) ? numeric : null;
}

function computeAxisBounds(axisSeries: ChartSeries[]): {
    min: number | undefined;
    max: number | undefined;
} {
    const allValues = axisSeries.flatMap((s) =>
        s.data.map((d) => d.value).filter((v) => Number.isFinite(v)),
    );
    const minLimits = axisSeries
        .map((s) => toFiniteNumber(s.min_val))
        .filter((v): v is number => v !== null);
    const maxLimits = axisSeries
        .map((s) => toFiniteNumber(s.max_val))
        .filter((v): v is number => v !== null);

    const hasPh = axisSeries.some((s) => s.label.toLowerCase().includes('ph'));
    const hasNegative =
        allValues.some((v) => v < 0) || minLimits.some((v) => v < 0);

    let computedMin: number | undefined = 0;

    if (hasPh || hasNegative) {
        const candidates = [...allValues, ...minLimits];

        if (candidates.length > 0) {
            const lowest = Math.min(...candidates);
            const highest = Math.max(...allValues, ...maxLimits, lowest);
            const rawRange = Math.abs(highest - lowest);
            const span =
                rawRange > 0
                    ? rawRange
                    : Math.max(Math.abs(lowest) * 0.2, 0.01);
            const precision = span < 0.5 ? 4 : 2;
            computedMin = Number((lowest - span * 0.15).toFixed(precision));
        } else {
            computedMin = undefined;
        }
    } else if (minLimits.length > 0 && allValues.length > 0) {
        const lowest = Math.min(...allValues, ...minLimits);
        computedMin = lowest < 0 ? Number((lowest * 1.15).toFixed(2)) : 0;
    }

    let computedMax: number | undefined = undefined;
    const maxCandidates = [...allValues, ...maxLimits, ...minLimits];

    if (
        maxCandidates.length > 0 &&
        (maxLimits.length > 0 || minLimits.length > 0)
    ) {
        const highest = Math.max(...maxCandidates);
        const lowest = computedMin ?? Math.min(...allValues, ...minLimits, 0);
        const rawRange = Math.abs(highest - lowest);
        const span =
            rawRange > 0 ? rawRange : Math.max(Math.abs(highest) * 0.2, 0.01);
        const precision = span < 0.5 ? 4 : 2;
        computedMax = Number((highest + span * 0.12).toFixed(precision));
    }

    return { min: computedMin, max: computedMax };
}

function renderChart() {
    if (!chartElement.value) {
        return;
    }

    chart ??= echarts.init(chartElement.value);
    const axes = new Map<string, number>();
    const yAxis: echarts.YAXisComponentOption[] = [];
    const seriesByAxis = new Map<string, ChartSeries[]>();

    for (const s of props.series) {
        const key = s.axis_position ?? 'left';
        const group = seriesByAxis.get(key) ?? [];
        group.push(s);
        seriesByAxis.set(key, group);
    }

    const seriesWithLimitsCount = props.series.filter(
        (s) =>
            toFiniteNumber(s.min_val) !== null ||
            toFiniteNumber(s.max_val) !== null,
    ).length;
    const hasMultipleLimitSeries = seriesWithLimitsCount > 1;

    const renderedSeries = props.series.map((series) => {
        const key = series.axis_position ?? 'left';
        let axisIndex = axes.get(key);

        if (axisIndex === undefined) {
            axisIndex = yAxis.length;
            axes.set(key, axisIndex);
            const group = seriesByAxis.get(key) ?? [series];
            const bounds = computeAxisBounds(group);

            yAxis.push({
                type: 'value',
                position: key,
                min: bounds.min,
                max: bounds.max,
                name: series.unit ?? '',
                axisLabel: {
                    formatter: (value: number) => formatNumericValue(value),
                },
                splitLine: { show: axisIndex === 0 },
            });
        }

        const isBar = series.chart_type === 'bar';
        const isArea = series.chart_type === 'area';
        const minVal = toFiniteNumber(series.min_val);
        const maxVal = toFiniteNumber(series.max_val);
        const limitColor = hasMultipleLimitSeries ? series.color : '#ef4444';

        const markLineData: Array<Record<string, unknown>> = [];

        if (minVal !== null) {
            const formattedMin = formatNumericValue(minVal, series.decimals);
            markLineData.push({
                yAxis: minVal,
                name: 'Limite Mín.',
                lineStyle: { color: limitColor, type: 'dashed', width: 1.5 },
                label: {
                    formatter: hasMultipleLimitSeries
                        ? `Mín (${series.label}): ${formattedMin}`
                        : `Mín: ${formattedMin}`,
                    position: 'insideStartTop',
                    color: limitColor,
                    fontSize: 10,
                    fontWeight: 600,
                    fontFamily: "'Poppins', sans-serif",
                },
            });
        }

        if (maxVal !== null) {
            const formattedMax = formatNumericValue(maxVal, series.decimals);
            markLineData.push({
                yAxis: maxVal,
                name: 'Limite Máx.',
                lineStyle: { color: limitColor, type: 'dashed', width: 1.5 },
                label: {
                    formatter: hasMultipleLimitSeries
                        ? `Máx (${series.label}): ${formattedMax}`
                        : `Máx: ${formattedMax}`,
                    position: 'insideEndTop',
                    color: limitColor,
                    fontSize: 10,
                    fontWeight: 600,
                    fontFamily: "'Poppins', sans-serif",
                },
            });
        }

        const dataPoints = series.data.map((point) => {
            const isOutOfBounds =
                (minVal !== null && point.value < minVal) ||
                (maxVal !== null && point.value > maxVal);

            if (isBar && isOutOfBounds) {
                return {
                    value: [point.timestamp, point.value],
                    itemStyle: { color: '#ef4444' },
                };
            }

            return [point.timestamp, point.value];
        });

        return {
            name: series.label,
            type: isBar ? ('bar' as const) : ('line' as const),
            yAxisIndex: axisIndex,
            data: dataPoints,
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
            markLine:
                markLineData.length > 0
                    ? {
                          symbol: ['none', 'none'],
                          silent: true,
                          data: markLineData,
                      }
                    : undefined,
        };
    });

    chart.setOption(
        {
            animation: false,
            textStyle: {
                fontFamily: "'Poppins', sans-serif",
            },
            tooltip: {
                trigger: 'axis',
                textStyle: {
                    fontFamily: "'Poppins', sans-serif",
                },
                formatter: (params: unknown) => {
                    const items = Array.isArray(params) ? params : [params];

                    if (!items.length) {
                        return '';
                    }

                    const first = items[0] as {
                        axisValue?: number | string;
                        value?: unknown;
                    };
                    const rawTime = Array.isArray(first.value)
                        ? first.value[0]
                        : first.axisValue;
                    const header = `<div style="font-weight:600;margin-bottom:4px;">Data/Hora: ${formatTooltipDateTime(rawTime as number | string)}</div>`;

                    const lines = items.map((item: any) => {
                        const val = Array.isArray(item.value)
                            ? item.value[1]
                            : item.value;
                        const matchedSeries =
                            props.series[item.seriesIndex ?? 0];
                        const unit = matchedSeries?.unit
                            ? ` ${matchedSeries.unit}`
                            : '';

                        return `<div>${item.marker ?? ''} <span>${item.seriesName}:</span> <strong>${formatNumericValue(Number(val))}${unit}</strong></div>`;
                    });

                    return header + lines.join('');
                },
            },
            legend: { type: 'scroll', top: 0 },
            grid: { left: 56, right: 56, top: 36, bottom: 58 },
            xAxis: {
                type: 'time',
                axisLabel: {
                    formatter: (value: number) => formatAxisDate(value),
                },
            },
            yAxis: yAxis.length > 0 ? yAxis : [{ type: 'value', min: 0 }],
            dataZoom: [
                {
                    type: 'inside',
                    zoomLock: true,
                    zoomOnMouseWheel: false,
                    moveOnMouseWheel: false,
                    preventDefaultMouseMove: false,
                },
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
