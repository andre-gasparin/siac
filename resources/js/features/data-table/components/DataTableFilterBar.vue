<script setup lang="ts">
import { Database, Loader2, Save } from '@lucide/vue';
import SystemMultiSelect from '@/features/data-table/components/SystemMultiSelect.vue';
import type { SystemItem } from '@/features/data-table/types';
import DatePicker from '@/shared/components/DatePicker.vue';

const selectedSystemIds = defineModel<number[]>('selectedSystemIds', {
    required: true,
});
const startDate = defineModel<string>('startDate', { required: true });
const endDate = defineModel<string>('endDate', { required: true });

defineProps<{
    systems: SystemItem[];
    isLoading: boolean;
    cacheStatus: string;
}>();

const emit = defineEmits<{
    load: [];
}>();
</script>

<template>
    <div
        class="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-3 text-xs shadow-sm"
    >
        <!-- Sistema Multiselect -->
        <div class="flex min-w-[240px] flex-1 flex-col gap-1">
            <label class="font-semibold text-foreground">Sistema</label>
            <SystemMultiSelect
                v-model="selectedSystemIds"
                :systems="systems"
                placeholder="Selecione o(s) sistema(s)"
            />
        </div>

        <!-- Data Inicial DatePicker -->
        <div class="flex w-full flex-col gap-1 sm:w-auto">
            <label class="font-semibold text-foreground">Data inicial</label>
            <DatePicker v-model="startDate" placeholder="Data inicial" />
        </div>

        <!-- Data Final DatePicker -->
        <div class="flex w-full flex-col gap-1 sm:w-auto">
            <label class="font-semibold text-foreground">Data Final</label>
            <DatePicker v-model="endDate" placeholder="Data final" />
        </div>

        <!-- Action Buttons & Cache Badge -->
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                @click="emit('load')"
                :disabled="isLoading"
                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow transition-colors hover:bg-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:outline-none disabled:opacity-50"
            >
                <Loader2 v-if="isLoading" class="h-3.5 w-3.5 animate-spin" />
                <Save v-else class="h-3.5 w-3.5" />
                <span>Carregar</span>
            </button>

            <div
                class="flex items-center gap-1.5 rounded-md border bg-muted/30 px-2.5 py-1.5 text-[11px] text-muted-foreground"
            >
                <Database class="h-3 w-3" />
                <span class="font-medium">Atualizado:</span>
                <span>{{ cacheStatus }}</span>
            </div>
        </div>
    </div>
</template>
