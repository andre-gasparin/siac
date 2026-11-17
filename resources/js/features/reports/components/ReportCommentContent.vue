<script setup lang="ts">
import { Loader2 } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import type { ReportChartConfig } from '@/features/reports/types';
import { data as chartDataRoute } from '@/routes/reports/charts';
import EChartRenderer from '@/shared/components/charts/EChartRenderer.vue';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';

type CommentSegment =
    | { type: 'html'; key: string; html: string }
    | {
          type: 'chart';
          key: string;
          chartId: string;
          title: string;
          height: number;
          startDate: string;
          endDate: string;
          config: Partial<ReportChartConfig> | null;
      };

const props = defineProps<{
    html: string;
    document?: Record<string, unknown> | null;
    charts?: Record<string, ChartSeries[]>;
    teamSlug?: string;
}>();

const fetchedCharts = reactive<Record<string, ChartSeries[]>>({});
const loadingCharts = reactive<Record<string, boolean>>({});

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatBrazilianDate(value: string): string {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return value;
    }

    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function collectDocumentCharts(
    node: Record<string, unknown> | null | undefined,
    byId: Map<string, Partial<ReportChartConfig>>,
    ordered: Array<Partial<ReportChartConfig>>,
): void {
    if (!node || typeof node !== 'object') {
        return;
    }

    if (
        node.type === 'reportChart' &&
        node.attrs &&
        typeof node.attrs === 'object'
    ) {
        const attrs = node.attrs as Partial<ReportChartConfig>;
        ordered.push(attrs);

        if (attrs.id) {
            byId.set(String(attrs.id), attrs);
        }
    }

    if (Array.isArray(node.content)) {
        for (const child of node.content) {
            if (child && typeof child === 'object') {
                collectDocumentCharts(
                    child as Record<string, unknown>,
                    byId,
                    ordered,
                );
            }
        }
    }
}

const segments = computed<CommentSegment[]>(() => {
    if (!props.html) {
        return [];
    }

    if (typeof DOMParser === 'undefined') {
        return [{ type: 'html', key: 'html-0', html: props.html }];
    }

    const byId = new Map<string, Partial<ReportChartConfig>>();
    const ordered: Array<Partial<ReportChartConfig>> = [];
    collectDocumentCharts(props.document, byId, ordered);

    const parsed = new DOMParser().parseFromString(props.html, 'text/html');
    const result: CommentSegment[] = [];
    let htmlBuffer = '';
    let segmentIndex = 0;
    let chartOrdinal = 0;

    const flushHtml = () => {
        if (htmlBuffer.trim() !== '') {
            result.push({
                type: 'html',
                key: `html-${segmentIndex++}`,
                html: htmlBuffer,
            });
        }

        htmlBuffer = '';
    };

    const walkNode = (node: Node) => {
        if (node.nodeType === Node.ELEMENT_NODE) {
            const element = node as HTMLElement;

            if (element.matches('[data-report-chart]')) {
                flushHtml();

                const rawId =
                    element.getAttribute('data-chart-id') ||
                    element.getAttribute('data-report-chart') ||
                    '';

                let parsedConfig: Partial<ReportChartConfig> | null = null;
                const rawConfig = element.getAttribute('data-chart-config');

                if (rawConfig) {
                    try {
                        parsedConfig = JSON.parse(rawConfig);
                    } catch {
                        parsedConfig = null;
                    }
                }

                const docConfig =
                    (rawId ? byId.get(rawId) : undefined) ??
                    ordered[chartOrdinal] ??
                    null;
                chartOrdinal += 1;

                const config = docConfig ?? parsedConfig;
                const chartId =
                    rawId ||
                    (config?.id ? String(config.id) : `chart-${segmentIndex}`);
                const fallbackTitle =
                    element.textContent?.replace(/^Gráfico:\s*/i, '').trim() ||
                    'Gráfico';

                result.push({
                    type: 'chart',
                    key: `chart-${chartId}-${segmentIndex++}`,
                    chartId,
                    title: config?.title || fallbackTitle,
                    height: Number(config?.height) || 320,
                    startDate: config?.startDate || '',
                    endDate: config?.endDate || '',
                    config,
                });

                return;
            }

            if (element.querySelector('[data-report-chart]')) {
                Array.from(element.childNodes).forEach(walkNode);

                return;
            }

            htmlBuffer += element.outerHTML;

            return;
        }

        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent ?? '';

            if (text.trim() !== '') {
                htmlBuffer += escapeHtml(text);
            }
        }
    };

    Array.from(parsed.body.childNodes).forEach(walkNode);
    flushHtml();

    return result;
});

function hasPreloadedChart(chartId: string): boolean {
    return Boolean(
        props.charts &&
        !Array.isArray(props.charts) &&
        Object.prototype.hasOwnProperty.call(props.charts, chartId),
    );
}

function getChartSeries(chartId: string): ChartSeries[] {
    if (hasPreloadedChart(chartId)) {
        return props.charts?.[chartId] ?? [];
    }

    return fetchedCharts[chartId] ?? [];
}

async function fetchChartFallback(
    segment: Extract<CommentSegment, { type: 'chart' }>,
): Promise<void> {
    const teamSlug = segment.config?.teamSlug || props.teamSlug;

    if (
        hasPreloadedChart(segment.chartId) ||
        Object.prototype.hasOwnProperty.call(fetchedCharts, segment.chartId) ||
        loadingCharts[segment.chartId] ||
        !teamSlug ||
        !segment.startDate ||
        !segment.endDate ||
        !segment.config?.series?.length
    ) {
        return;
    }

    loadingCharts[segment.chartId] = true;

    try {
        const response = await fetch(
            chartDataRoute.url({ current_team: teamSlug }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    start_date: segment.startDate,
                    end_date: segment.endDate,
                    series: segment.config.series,
                }),
            },
        );

        fetchedCharts[segment.chartId] = response.ok
            ? (await response.json()).series
            : [];
    } catch {
        fetchedCharts[segment.chartId] = [];
    } finally {
        loadingCharts[segment.chartId] = false;
    }
}

watch(
    [segments, () => props.charts, () => props.teamSlug],
    () => {
        for (const segment of segments.value) {
            if (segment.type === 'chart') {
                void fetchChartFallback(segment);
            }
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="report-comment-content space-y-3">
        <template v-for="segment in segments" :key="segment.key">
            <div
                v-if="segment.type === 'html'"
                class="prose prose-sm dark:prose-invert max-w-none leading-relaxed text-foreground"
                v-html="segment.html"
            />
            <div
                v-else
                class="not-prose my-3 rounded-xl border border-border/70 bg-card p-3 shadow-2xs"
                data-report-chart-node
            >
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-foreground">
                            {{ segment.title || 'Gráfico' }}
                        </p>
                        <p
                            v-if="segment.startDate && segment.endDate"
                            class="text-[11px] text-muted-foreground"
                        >
                            {{ formatBrazilianDate(segment.startDate) }} —
                            {{ formatBrazilianDate(segment.endDate) }}
                        </p>
                    </div>
                    <Loader2
                        v-if="loadingCharts[segment.chartId]"
                        class="size-4 animate-spin text-muted-foreground"
                    />
                </div>
                <EChartRenderer
                    v-if="getChartSeries(segment.chartId).length"
                    :series="getChartSeries(segment.chartId)"
                    :height="segment.height"
                />
                <p
                    v-else-if="!loadingCharts[segment.chartId]"
                    class="py-8 text-center text-xs text-muted-foreground"
                >
                    Sem dados para o período configurado.
                </p>
            </div>
        </template>
    </div>
</template>

<style scoped>
.report-comment-content :deep(.report-icon-error) {
    color: #dc2626;
}
.report-comment-content :deep(.report-icon-warning) {
    color: #d97706;
}
.report-comment-content :deep(.report-icon-success) {
    color: #059669;
}
.report-comment-content :deep(.report-icon-info) {
    color: #2563eb;
}
</style>
