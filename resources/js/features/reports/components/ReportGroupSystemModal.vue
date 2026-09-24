<script setup lang="ts">
import { AlertCircle, Layers } from '@lucide/vue';

interface SystemSectionItem {
    id: number;
    name: string;
    item: {
        comment: string | null;
    } | null;
}

const targetSystemId = defineModel<number | null>('targetSystemId', {
    required: true,
});

defineProps<{
    show: boolean;
    sourceSystem: SystemSectionItem | null;
    availableTargets: SystemSectionItem[];
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>

<template>
    <div
        v-if="show && sourceSystem"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
    >
        <div
            class="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-xl"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
                >
                    <Layers class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-foreground">
                        Agrupar Sistema
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Selecione em qual sistema o
                        <strong class="font-semibold text-foreground">
                            "{{ sourceSystem.name }}"
                        </strong>
                        será anexado:
                    </p>
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1.5 block text-xs font-medium text-foreground">
                    Sistema Principal:
                </label>
                <select
                    v-model="targetSystemId"
                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs text-foreground shadow-xs focus:ring-2 focus:ring-emerald-500 focus:outline-hidden"
                >
                    <option
                        v-for="target in availableTargets"
                        :key="target.id"
                        :value="target.id"
                    >
                        {{ target.name }}
                    </option>
                </select>
            </div>

            <div
                v-if="
                    Boolean(
                        sourceSystem.item?.comment &&
                        sourceSystem.item.comment
                            .replace(/<[^>]+>/g, '')
                            .trim(),
                    )
                "
                class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300"
            >
                <div class="flex items-start gap-2">
                    <AlertCircle
                        class="mt-0.5 size-4 shrink-0 text-amber-600"
                    />
                    <p>
                        Atenção: O sistema "{{ sourceSystem.name }}" possui
                        considerações. Ao agrupar, os comentários deste sistema
                        serão permanentemente removidos.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2.5">
                <button
                    type="button"
                    class="rounded-xl border border-border px-3.5 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700"
                    @click="emit('confirm')"
                >
                    Confirmar e Agrupar
                </button>
            </div>
        </div>
    </div>
</template>
