<script setup lang="ts">
import { X } from '@lucide/vue';
import type { ReportPhrase } from '@/features/reports/types';

const selectedPhraseIds = defineModel<number[]>('selectedPhraseIds', {
    required: true,
});
const sourcePhraseId = defineModel<number | null>('sourcePhraseId', {
    required: true,
});

defineProps<{
    phrases?: ReportPhrase[];
}>();

const emit = defineEmits<{
    close: [];
    group: [];
    ungroup: [phrase: ReportPhrase];
}>();
</script>

<template>
    <aside
        class="fixed inset-0 z-[105] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div
            class="max-h-[80vh] w-full max-w-3xl overflow-y-auto rounded-2xl border bg-card p-5 shadow-2xl"
        >
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-foreground">
                        Frases e agrupamento
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Selecione os sistemas e escolha a frase fonte
                    </p>
                </div>
                <button
                    class="rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                    title="Fechar"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                    <span class="sr-only">Fechar</span>
                </button>
            </div>
            <div
                v-for="phrase in phrases"
                :key="phrase.id"
                class="flex items-start gap-2 border-b py-2 text-xs text-foreground"
            >
                <input
                    v-model="selectedPhraseIds"
                    type="checkbox"
                    :value="phrase.id"
                />
                <div class="flex-1">
                    <strong>{{ phrase.system_name }}</strong>
                    <p class="line-clamp-2 text-muted-foreground">
                        {{ phrase.html.replace(/<[^>]+>/g, ' ') }}
                    </p>
                </div>
                <button
                    v-if="phrase.parent_report_item_id"
                    type="button"
                    class="font-medium text-red-600 hover:underline"
                    @click="emit('ungroup', phrase)"
                >
                    Desagrupar
                </button>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <select
                    v-model.number="sourcePhraseId"
                    class="h-8 rounded border bg-background px-2 text-xs text-foreground"
                >
                    <option :value="null">Frase fonte</option>
                    <option
                        v-for="phrase in phrases?.filter((p) =>
                            selectedPhraseIds.includes(p.id),
                        )"
                        :key="phrase.id"
                        :value="phrase.id"
                    >
                        {{ phrase.system_name }}
                    </option>
                </select>
                <button
                    type="button"
                    class="rounded bg-blue-600 px-3 py-2 text-xs text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                    :disabled="selectedPhraseIds.length < 2 || !sourcePhraseId"
                    @click="emit('group')"
                >
                    Agrupar selecionados
                </button>
            </div>
        </div>
    </aside>
</template>
