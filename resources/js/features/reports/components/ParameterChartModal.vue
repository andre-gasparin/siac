<script setup lang="ts">
import { Loader2, X } from '@lucide/vue';
import { watch, ref } from 'vue';
import EChartRenderer from '@/shared/components/charts/EChartRenderer.vue';
import type { ChartSeries } from '@/shared/components/charts/EChartRenderer.vue';

const props = defineProps<{
    open: boolean;
    url: string;
    title: string;
}>();

const emit = defineEmits<{ close: [] }>();
const series = ref<ChartSeries[]>([]);
const loading = ref(false);

async function load() {
    if (!props.open || !props.url) {
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(props.url, {
            headers: { Accept: 'application/json' },
        });
        series.value = response.ok ? (await response.json()).series : [];
    } finally {
        loading.value = false;
    }
}

watch(() => [props.open, props.url], load, { immediate: true });
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <section
            class="w-full max-w-4xl rounded-2xl border bg-card p-5 shadow-2xl"
        >
            <header class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">{{ title }}</h2>
                    <p class="text-xs text-muted-foreground">
                        Sete dias até a data do relatório · passe o cursor para
                        ver os valores
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 hover:bg-muted"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </header>
            <div v-if="loading" class="grid h-72 place-items-center">
                <Loader2 class="size-7 animate-spin text-blue-600" />
            </div>
            <EChartRenderer
                v-else-if="series.length"
                :series="series"
                :height="380"
            />
            <p v-else class="py-20 text-center text-sm text-muted-foreground">
                Sem dados para o período.
            </p>
        </section>
    </div>
</template>
