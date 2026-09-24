<script setup lang="ts">
import { Loader2, Pencil } from '@lucide/vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import type { NodeViewProps } from '@tiptap/vue-3';
import { onMounted, ref, watch } from 'vue';
import { data as chartDataRoute } from '@/routes/reports/charts';
import EChartRenderer from '@/shared/components/charts/EChartRenderer.vue';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';

const props = defineProps<NodeViewProps>();
const series = ref<ChartSeries[]>([]);
const isLoading = ref(false);

function requestEdit(event: MouseEvent) {
    const position = props.getPos();

    if (position === undefined) {
        return;
    }

    (event.currentTarget as HTMLElement).dispatchEvent(
        new CustomEvent('report-chart-edit', {
            bubbles: true,
            detail: {
                position,
                chart: { ...props.node.attrs },
            },
        }),
    );
}

async function load() {
    const attrs = props.node.attrs;

    if (!attrs.teamSlug || !attrs.series?.length) {
        series.value = [];

        return;
    }

    isLoading.value = true;

    try {
        const response = await fetch(
            chartDataRoute.url({ current_team: attrs.teamSlug }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    start_date: attrs.startDate,
                    end_date: attrs.endDate,
                    series: attrs.series,
                }),
            },
        );

        series.value = response.ok ? (await response.json()).series : [];
    } finally {
        isLoading.value = false;
    }
}

watch(() => props.node.attrs, load, { deep: true });
onMounted(load);
</script>

<template>
    <NodeViewWrapper
        class="my-3 rounded-lg border bg-card p-3"
        data-report-chart-node
    >
        <div class="mb-2 flex items-center justify-between gap-2">
            <div>
                <p class="text-sm font-semibold">
                    {{ node.attrs.title || 'Gráfico' }}
                </p>
                <p class="text-[11px] text-muted-foreground">
                    {{ node.attrs.startDate }} — {{ node.attrs.endDate }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Loader2 v-if="isLoading" class="size-4 animate-spin" />
                <button
                    type="button"
                    data-test="edit-report-chart"
                    class="inline-flex items-center gap-1.5 rounded-lg border bg-background px-2.5 py-1.5 text-xs font-medium hover:bg-muted"
                    @click.stop="requestEdit"
                >
                    <Pencil class="size-3.5" /> Editar gráfico
                </button>
            </div>
        </div>
        <EChartRenderer
            v-if="series.length"
            :series="series"
            :height="node.attrs.height"
        />
        <p
            v-else-if="!isLoading"
            class="py-8 text-center text-xs text-muted-foreground"
        >
            Sem dados para o período configurado.
        </p>
    </NodeViewWrapper>
</template>
