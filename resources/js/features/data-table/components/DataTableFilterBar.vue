<script setup lang="ts">
import { ChevronLeft, ChevronRight, Loader2, Save } from '@lucide/vue';
import { computed } from 'vue';
import SystemMultiSelect from '@/features/data-table/components/SystemMultiSelect.vue';
import type { SystemItem } from '@/features/data-table/types';
import DatePicker from '@/shared/components/DatePicker.vue';

const selectedSystemIds = defineModel<number[]>('selectedSystemIds', {
    required: true,
});
const startDate = defineModel<string>('startDate', { required: true });
const endDate = defineModel<string>('endDate', { required: true });
const props = defineProps<{
    systems: SystemItem[];
    isLoading: boolean;
}>();
const emit = defineEmits<{ load: [] }>();
const currentIndex = computed(() =>
    props.systems.findIndex(
        (system) => system.id === selectedSystemIds.value[0],
    ),
);
function moveSystem(offset: number) {
    const system = props.systems[currentIndex.value + offset];

    if (!system || props.isLoading) {
        return;
    }

    selectedSystemIds.value = [system.id];
    emit('load');
}
</script>

<template>
    <div
        class="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-3 text-xs shadow-sm"
    >
        <div class="flex min-w-[240px] flex-1 flex-col gap-1">
            <label class="font-semibold">Sistema</label>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="h-9 rounded border px-2 hover:bg-accent disabled:opacity-40"
                    :disabled="isLoading || currentIndex <= 0"
                    title="Sistema anterior"
                    @click="moveSystem(-1)"
                >
                    <ChevronLeft class="size-4" />
                </button>
                <SystemMultiSelect
                    v-model="selectedSystemIds"
                    :systems="systems"
                    placeholder="Selecione os sistemas"
                    @select="emit('load')"
                />
                <button
                    type="button"
                    class="h-9 rounded border px-2 hover:bg-accent disabled:opacity-40"
                    :disabled="
                        isLoading ||
                        currentIndex < 0 ||
                        currentIndex >= systems.length - 1
                    "
                    title="Próximo sistema"
                    @click="moveSystem(1)"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>
        </div>
        <div class="flex w-full flex-col gap-1 sm:w-auto">
            <label class="font-semibold">Data inicial</label
            ><DatePicker v-model="startDate" placeholder="Data inicial" />
        </div>
        <div class="flex w-full flex-col gap-1 sm:w-auto">
            <label class="font-semibold">Data final</label
            ><DatePicker v-model="endDate" placeholder="Data final" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                :disabled="isLoading"
                class="inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                @click="emit('load')"
            >
                <Loader2 v-if="isLoading" class="size-3.5 animate-spin" /><Save
                    v-else
                    class="size-3.5"
                />Carregar
            </button>
        </div>
    </div>
</template>
