<script setup lang="ts">
import { AlertTriangle, X } from '@lucide/vue';
import {
    formatLimitsLabel,
    formatNumericValue,
    formatReportHour,
    getCategoryMeta,
} from '@/features/reports/reportMetrics';
import type { DeviationEntry } from '@/features/reports/reportMetrics';

defineProps<{
    open: boolean;
    systemName: string;
    deviations: DeviationEntry[];
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[120] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        @click.self="emit('close')"
    >
        <section
            role="dialog"
            aria-modal="true"
            class="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl border border-border bg-card text-card-foreground shadow-2xl"
        >
            <header
                class="flex items-start justify-between gap-3 border-b border-border px-5 py-4"
            >
                <div class="flex items-start gap-2.5">
                    <div
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-red-500/15 text-red-600 dark:text-red-400"
                    >
                        <AlertTriangle class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Resultados em desvio
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ systemName }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    aria-label="Fechar"
                    class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </header>

            <div class="flex-1 overflow-y-auto p-5">
                <p class="mb-3 text-xs text-muted-foreground">
                    {{ deviations.length }}
                    {{ deviations.length === 1 ? 'resultado' : 'resultados' }}
                    em desvio ·
                    {{ new Set(deviations.map((d) => d.parameter.id)).size }}
                    parâmetro(s)
                </p>

                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full border-collapse text-xs tabular-nums">
                        <thead>
                            <tr
                                class="border-b border-border bg-muted/60 text-left text-[11px] font-semibold text-muted-foreground"
                            >
                                <th class="px-3 py-2">Parâmetro</th>
                                <th class="px-3 py-2 text-center">Tipo</th>
                                <th class="px-3 py-2 text-center">Horário</th>
                                <th class="px-3 py-2 text-center">Resultado</th>
                                <th class="px-3 py-2 text-center">Limite</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="(entry, idx) in deviations"
                                :key="`${entry.parameter.id}-${entry.timestamp}-${idx}`"
                                class="hover:bg-muted/30"
                            >
                                <td
                                    class="px-3 py-2 font-medium text-foreground"
                                >
                                    {{ entry.parameter.name }}
                                    <span
                                        v-if="entry.parameter.unit"
                                        class="text-muted-foreground"
                                    >
                                        ({{ entry.parameter.unit }})
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        v-if="
                                            getCategoryMeta(
                                                entry.parameter.category,
                                            )
                                        "
                                        class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                        :class="
                                            getCategoryMeta(
                                                entry.parameter.category,
                                            )?.badgeClass
                                        "
                                    >
                                        {{
                                            getCategoryMeta(
                                                entry.parameter.category,
                                            )?.code
                                        }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td
                                    class="px-3 py-2 text-center whitespace-nowrap text-muted-foreground"
                                >
                                    {{ formatReportHour(entry.time) }}
                                </td>
                                <td
                                    class="px-3 py-2 text-center font-bold text-red-600 dark:text-red-400"
                                >
                                    {{
                                        formatNumericValue(
                                            entry.value,
                                            entry.parameter.decimals,
                                        )
                                    }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="inline-block rounded-full bg-emerald-100/90 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                    >
                                        {{ formatLimitsLabel(entry.parameter) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>
