<script setup lang="ts">
import { MessageSquareText, X } from '@lucide/vue';
import {
    formatDateTimeBr,
    formatReportHour,
} from '@/features/reports/reportMetrics';
import type { RowObservation } from '@/features/reports/types';

defineProps<{
    open: boolean;
    dateLabel: string;
    timeLabel: string;
    observations: RowObservation[];
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
            aria-labelledby="registro-obs-title"
            class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl border border-border bg-card text-card-foreground shadow-2xl"
        >
            <header
                class="flex items-start justify-between gap-3 border-b border-border px-5 py-4"
            >
                <div class="flex items-start gap-2.5">
                    <div
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <MessageSquareText class="size-4" />
                    </div>
                    <div>
                        <h2
                            id="registro-obs-title"
                            class="text-sm font-semibold text-foreground"
                        >
                            Observações dos dados
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ dateLabel }} · {{ formatReportHour(timeLabel) }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    aria-label="Fechar observações"
                    class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </header>

            <div class="flex-1 space-y-3 overflow-y-auto p-5">
                <div
                    v-if="observations.length === 0"
                    class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                >
                    Nenhuma observação cadastrada para este horário.
                </div>

                <article
                    v-for="(obs, index) in observations"
                    :key="`${obs.system_id}-${index}`"
                    class="rounded-xl border border-border/80 bg-muted/20 p-3.5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <strong class="text-xs font-semibold text-foreground">
                            {{ obs.system_name }}
                        </strong>
                        <span
                            v-if="obs.author || obs.created_at"
                            class="text-[11px] text-muted-foreground"
                        >
                            {{ obs.author ?? 'Usuário' }}
                            <template v-if="obs.created_at">
                                · {{ formatDateTimeBr(obs.created_at) }}
                            </template>
                        </span>
                    </div>
                    <p
                        class="mt-2 text-xs leading-relaxed whitespace-pre-wrap text-foreground/90"
                    >
                        {{ obs.comment }}
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>
