<script setup lang="ts">
import {
    Check,
    LayoutGrid,
    Plus,
    Share2,
    Sparkles,
    Trash2,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import type { DashboardItem } from '@/features/dashboards/types';
import { Button } from '@/shared/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/shared/components/ui/tooltip';

defineProps<{
    dashboards: DashboardItem[];
    activeDashboard: DashboardItem | null;
}>();

const emit = defineEmits<{
    selectDashboard: [id: number];
    openAiModal: [];
    openCreateModal: [];
    deleteDashboard: [dashboard: DashboardItem];
}>();

const showDeleteConfirmation = ref(false);

function confirmDelete(dashboard: DashboardItem) {
    showDeleteConfirmation.value = false;
    emit('deleteDashboard', dashboard);
}
</script>

<template>
    <section
        class="flex flex-col gap-4 border-b border-slate-200 pb-5 dark:border-neutral-800"
    >
        <div class="flex items-center gap-3">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
                <LayoutGrid class="size-5" />
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-xl font-bold text-slate-950 md:text-2xl dark:text-white"
                    >
                        {{ activeDashboard?.title || 'Selecione um Dashboard' }}
                    </h1>
                    <span
                        v-if="activeDashboard?.is_public"
                        class="flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-950/50 dark:text-blue-300"
                    >
                        <Share2 class="size-3" /> Público
                    </span>
                </div>
                <p class="text-xs text-muted-foreground">
                    Grade responsiva de até 12 colunas com compactação
                    automática.
                </p>
            </div>
        </div>

        <TooltipProvider :delay-duration="200">
            <div class="flex items-center gap-2">
                <select
                    aria-label="Selecionar dashboard"
                    :value="activeDashboard?.id"
                    @change="
                        emit(
                            'selectDashboard',
                            Number(($event.target as HTMLSelectElement).value),
                        )
                    "
                    class="h-9 min-w-0 flex-1 truncate rounded-md border bg-background px-3 text-xs font-medium shadow-xs focus-visible:ring-2 focus-visible:ring-ring sm:max-w-lg"
                >
                    <option v-for="d in dashboards" :key="d.id" :value="d.id">
                        {{ d.title }}
                        {{ d.is_public ? '(Público)' : '(Privado)' }}
                    </option>
                </select>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Criar com IA"
                            @click="emit('openAiModal')"
                        >
                            <Sparkles aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Criar com IA</TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Novo Dashboard"
                            @click="emit('openCreateModal')"
                        >
                            <Plus aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Novo Dashboard</TooltipContent>
                </Tooltip>
                <div v-if="activeDashboard" class="relative shrink-0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Excluir dashboard"
                                :aria-expanded="showDeleteConfirmation"
                                @click="
                                    showDeleteConfirmation =
                                        !showDeleteConfirmation
                                "
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Excluir dashboard</TooltipContent>
                    </Tooltip>
                    <div
                        v-if="showDeleteConfirmation"
                        role="dialog"
                        aria-label="Confirmar exclusão do dashboard"
                        class="absolute top-full right-0 z-50 mt-2 flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-medium whitespace-nowrap text-slate-700 shadow-xl dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200"
                    >
                        <span class="px-1">Excluir?</span>
                        <button
                            type="button"
                            aria-label="Confirmar exclusão do dashboard"
                            title="Confirmar exclusão"
                            class="flex size-7 items-center justify-center rounded-md bg-red-600 text-white hover:bg-red-700"
                            @click="confirmDelete(activeDashboard)"
                        >
                            <Check class="size-4" />
                        </button>
                        <button
                            type="button"
                            aria-label="Cancelar exclusão do dashboard"
                            title="Cancelar exclusão"
                            class="flex size-7 items-center justify-center rounded-md bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700"
                            @click="showDeleteConfirmation = false"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </TooltipProvider>
    </section>
</template>
