<script setup lang="ts">
import { Check, Loader2, X as XIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { ActiveEditingRowDate } from '@/features/data-table/types';

const props = defineProps<{
    rowDate: ActiveEditingRowDate | null;
    isSaving: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    close: [];
    save: [payload: { newDate: string; newTime: string }];
}>();

const localNewDate = ref('');
const localNewTime = ref('');

const editingRowDatePos = computed(() => {
    if (!props.rowDate) {
        return { x: 0, y: 0 };
    }

    const width = 300;
    const height = 230;

    let x = props.rowDate.x - width / 2;
    let y = props.rowDate.y;

    if (typeof window !== 'undefined') {
        if (x + width > window.innerWidth - 16) {
            x = window.innerWidth - width - 16;
        }

        if (x < 16) {
            x = 16;
        }

        if (y + height > window.innerHeight - 16) {
            y = Math.max(16, props.rowDate.yUpper - height - 8);
        }
    }

    return { x, y };
});

watch(
    () => props.rowDate,
    (newRowDate) => {
        if (newRowDate) {
            localNewDate.value = newRowDate.newDate;
            localNewTime.value = newRowDate.newTime;
        }
    },
    { immediate: true },
);

function handleSave() {
    emit('save', {
        newDate: localNewDate.value,
        newTime: localNewTime.value,
    });
}
</script>

<template>
    <div
        v-if="rowDate"
        class="fixed inset-0 z-50 flex items-start justify-start bg-black/15 backdrop-blur-[0.5px]"
        @click.self="emit('close')"
    >
        <div
            class="fixed z-50 w-72 animate-in rounded-lg border bg-popover p-3 text-popover-foreground shadow-xl transition-all fade-in-0 zoom-in-95"
            :style="{
                top: `${editingRowDatePos.y}px`,
                left: `${editingRowDatePos.x}px`,
            }"
        >
            <div class="mb-2.5 flex items-center justify-between border-b pb-2">
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-foreground">
                        Editar Data e Hora da Linha
                    </span>
                    <span class="text-[10px] text-muted-foreground">
                        Altera a data/hora de todas as leituras desta linha
                    </span>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                >
                    <XIcon class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="flex flex-col gap-2.5">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label
                            class="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Nova Data
                        </label>
                        <input
                            type="date"
                            v-model="localNewDate"
                            @keydown.enter.prevent="handleSave"
                            @keydown.esc="emit('close')"
                            class="w-full rounded-md border bg-background px-2 py-1 text-xs text-foreground shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Nova Hora
                        </label>
                        <input
                            type="time"
                            v-model="localNewTime"
                            @keydown.enter.prevent="handleSave"
                            @keydown.esc="emit('close')"
                            class="w-full rounded-md border bg-background px-2 py-1 text-xs text-foreground shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                        />
                    </div>
                </div>

                <div
                    v-if="error"
                    class="rounded bg-red-50 p-1.5 text-[11px] text-red-600 dark:bg-red-950/40 dark:text-red-300"
                >
                    {{ error }}
                </div>

                <div class="mt-1 flex items-center justify-between gap-1.5">
                    <span class="text-[10px] text-muted-foreground">
                        Pressione Enter ↵
                    </span>

                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="emit('close')"
                            :disabled="isSaving"
                            class="rounded-md border px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            @click="handleSave"
                            :disabled="isSaving"
                            class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-3 py-1 text-xs font-semibold text-white shadow transition-colors hover:bg-emerald-700 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="isSaving"
                                class="h-3 w-3 animate-spin"
                            />
                            <Check v-else class="h-3 w-3" />
                            <span>Salvar</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
