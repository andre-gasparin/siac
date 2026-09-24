<script setup lang="ts">
import { Copy, Loader2, RefreshCw, X } from '@lucide/vue';
import type { ReportHistoryItem } from '@/features/reports/types';

const historyDate = defineModel<string>('historyDate', { required: true });

defineProps<{
    history: ReportHistoryItem[];
    isLoading: boolean;
}>();

const emit = defineEmits<{
    close: [];
    filter: [];
    useHistorical: [item: ReportHistoryItem, mode: 'append' | 'replace'];
}>();

function formatBrazilianDate(value: string): string {
    if (!value) {
        return '';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}
</script>

<template>
    <aside
        class="fixed inset-0 z-[105] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div
            class="max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-2xl border bg-card p-5 shadow-2xl"
        >
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-foreground">
                        Relatórios anteriores
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Copie um documento anterior para o editor
                    </p>
                </div>
                <button
                    data-test="close-history"
                    class="rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                    title="Fechar"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                    <span class="sr-only">Fechar</span>
                </button>
            </div>
            <div
                class="mb-4 flex items-center gap-2 rounded-xl bg-muted/35 p-2"
            >
                <input
                    v-model="historyDate"
                    type="date"
                    class="h-9 flex-1 rounded-lg border bg-background px-3 text-xs text-foreground"
                />
                <button
                    class="inline-flex h-9 items-center gap-2 rounded-lg bg-blue-600 px-4 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                    :disabled="isLoading"
                    @click="emit('filter')"
                >
                    <Loader2 v-if="isLoading" class="size-3.5 animate-spin" />
                    <RefreshCw v-else class="size-3.5" />
                    Filtrar
                </button>
            </div>
            <div
                v-if="isLoading"
                class="grid min-h-48 place-items-center rounded-xl border border-dashed"
            >
                <div class="text-center">
                    <Loader2
                        class="mx-auto size-6 animate-spin text-blue-600"
                    />
                    <p class="mt-2 text-xs text-muted-foreground">
                        Carregando comentário e formatação…
                    </p>
                </div>
            </div>
            <div
                v-for="item in isLoading ? [] : history"
                :key="item.id"
                class="mb-3 rounded-xl border bg-card p-4 text-xs shadow-sm transition hover:border-blue-300 hover:shadow-md"
            >
                <strong
                    class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
                >
                    {{ formatBrazilianDate(item.date_reference) }}
                </strong>
                <p class="mt-3 leading-relaxed text-foreground/80">
                    {{ item.preview }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-700"
                        @click="emit('useHistorical', item, 'append')"
                    >
                        <Copy class="size-3.5" /> Adicionar ao final
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 px-3 py-2 font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30"
                        @click="emit('useHistorical', item, 'replace')"
                    >
                        <RefreshCw class="size-3.5" /> Sobrescrever
                    </button>
                </div>
            </div>
            <p
                v-if="!isLoading && history.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-xs text-muted-foreground"
            >
                Nenhum comentário anterior.
            </p>
        </div>
    </aside>
</template>
