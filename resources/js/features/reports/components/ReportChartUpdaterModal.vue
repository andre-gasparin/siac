<script setup lang="ts">
import { X } from '@lucide/vue';

const bulkChartIds = defineModel<string[]>('bulkChartIds', {
    required: true,
});

defineProps<{
    show: boolean;
    chartNodes: Array<Record<string, any>>;
}>();

const emit = defineEmits<{
    close: [];
    updatePeriods: [days?: number, shift?: boolean];
}>();
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
    >
        <div class="w-full max-w-xl rounded-xl bg-card p-4 shadow-2xl">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-foreground">
                    Atualizar gráficos
                </h3>
                <button
                    type="button"
                    class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </div>
            <label
                v-for="chart in chartNodes"
                :key="chart.id"
                class="mt-2 flex items-center gap-2 rounded border p-2 text-xs text-foreground"
            >
                <input
                    v-model="bulkChartIds"
                    type="checkbox"
                    :value="chart.id"
                />
                <span class="flex-1">
                    <strong>{{ chart.title }}</strong>
                    <br />
                    <span class="text-muted-foreground">
                        {{ chart.startDate }} — {{ chart.endDate }}
                    </span>
                </span>
            </label>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="days in [7, 10, 15, 30]"
                    :key="days"
                    type="button"
                    class="rounded border px-3 py-2 text-xs text-foreground transition-colors hover:bg-muted"
                    @click="emit('updatePeriods', days, false)"
                >
                    {{ days }} dias
                </button>
                <button
                    type="button"
                    class="rounded border px-3 py-2 text-xs text-foreground transition-colors hover:bg-muted"
                    @click="emit('updatePeriods', undefined, true)"
                >
                    +1 dia
                </button>
            </div>
        </div>
    </div>
</template>
