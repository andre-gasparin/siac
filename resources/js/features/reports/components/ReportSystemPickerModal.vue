<script setup lang="ts">
import type { ReportSystem } from '@/features/reports/types';

const chosenSystemId = defineModel<number | null>('chosenSystemId', {
    required: true,
});

defineProps<{
    show: boolean;
    availableSystems: ReportSystem[];
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4"
    >
        <div class="w-full max-w-sm rounded-xl border bg-card p-4 shadow-2xl">
            <h3 class="font-semibold text-foreground">Escolha o sistema</h3>
            <select
                v-model.number="chosenSystemId"
                class="mt-3 h-10 w-full rounded-md border bg-background px-3 text-sm text-foreground"
            >
                <option
                    v-for="system in availableSystems"
                    :key="system.id"
                    :value="system.id"
                >
                    {{ system.name }}
                </option>
            </select>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    class="rounded-md border px-3 py-2 text-xs text-foreground transition-colors hover:bg-muted"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <button
                    class="rounded-md bg-blue-600 px-3 py-2 text-xs text-white transition-colors hover:bg-blue-700"
                    @click="emit('confirm')"
                >
                    Abrir editor
                </button>
            </div>
        </div>
    </div>
</template>
