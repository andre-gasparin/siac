<script setup lang="ts">
import { Activity, BarChart3, Calendar, FileText, Loader2 } from '@lucide/vue';

const selectedPreset = defineModel<'7d' | '30d' | '90d' | 'custom'>(
    'selectedPreset',
    { required: true },
);

defineProps<{
    hasActiveDashboard: boolean;
    addingComponentType: 'indicator' | 'text' | 'chart' | null;
}>();

const emit = defineEmits<{
    addComponent: [type: 'indicator' | 'text' | 'chart'];
}>();
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between dark:border-neutral-800 dark:bg-neutral-900"
    >
        <!-- Global Date Filter (7 Days default) -->
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span
                class="flex items-center gap-1.5 font-medium text-slate-700 dark:text-neutral-300"
            >
                <Calendar class="size-4 text-primary" />
                Período:
            </span>

            <button
                type="button"
                @click="selectedPreset = '7d'"
                class="rounded px-2.5 py-1 text-xs font-medium transition"
                :class="
                    selectedPreset === '7d'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-neutral-800 dark:text-neutral-300'
                "
            >
                Últimos 7 Dias (Padrão)
            </button>
            <button
                type="button"
                @click="selectedPreset = '30d'"
                class="rounded px-2.5 py-1 text-xs font-medium transition"
                :class="
                    selectedPreset === '30d'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-neutral-800 dark:text-neutral-300'
                "
            >
                30 Dias
            </button>
            <button
                type="button"
                @click="selectedPreset = '90d'"
                class="rounded px-2.5 py-1 text-xs font-medium transition"
                :class="
                    selectedPreset === '90d'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-neutral-800 dark:text-neutral-300'
                "
            >
                90 Dias
            </button>
        </div>

        <!-- Add Component Actions -->
        <div
            v-if="hasActiveDashboard"
            class="flex flex-wrap items-center gap-2"
        >
            <span class="text-xs font-medium text-slate-500">
                Adicionar ao Grid:
            </span>
            <button
                type="button"
                @click="emit('addComponent', 'indicator')"
                :disabled="addingComponentType !== null"
                :aria-busy="addingComponentType === 'indicator'"
                class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-wait disabled:opacity-60 dark:border-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <Loader2
                    v-if="addingComponentType === 'indicator'"
                    class="size-3.5 animate-spin"
                />
                <Activity v-else class="size-3.5 text-blue-500" />
                {{
                    addingComponentType === 'indicator'
                        ? 'Adicionando...'
                        : 'Indicador'
                }}
            </button>
            <button
                type="button"
                @click="emit('addComponent', 'chart')"
                :disabled="addingComponentType !== null"
                :aria-busy="addingComponentType === 'chart'"
                class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-wait disabled:opacity-60 dark:border-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <Loader2
                    v-if="addingComponentType === 'chart'"
                    class="size-3.5 animate-spin"
                />
                <BarChart3 v-else class="size-3.5 text-emerald-500" />
                {{
                    addingComponentType === 'chart'
                        ? 'Adicionando...'
                        : 'Gráfico'
                }}
            </button>
            <button
                type="button"
                @click="emit('addComponent', 'text')"
                :disabled="addingComponentType !== null"
                :aria-busy="addingComponentType === 'text'"
                class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-wait disabled:opacity-60 dark:border-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <Loader2
                    v-if="addingComponentType === 'text'"
                    class="size-3.5 animate-spin"
                />
                <FileText v-else class="size-3.5 text-purple-500" />
                {{
                    addingComponentType === 'text' ? 'Adicionando...' : 'Texto'
                }}
            </button>
        </div>
    </section>
</template>
