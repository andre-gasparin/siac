<script setup lang="ts">
import { Grid } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import DashboardComponentItem from '@/features/dashboards/components/DashboardComponentItem.vue';
import type { CanvasRect, ComponentItem } from '@/features/dashboards/types';

defineProps<{
    localComponents: ComponentItem[];
    orderedComponents: ComponentItem[];
    activeComponentId: number | null;
    isInteracting: boolean;
    canvasHeight: string;
    isCompactCanvas: boolean;
    draggingComponent: ComponentItem | null;
    dragPreview: CanvasRect | null;
    dragOriginRect: CanvasRect | null;
    dropTargetId: number | null;
    resizingComponent: ComponentItem | null;
    resizeHasCollision: boolean;
    componentRect: (component: ComponentItem) => CanvasRect;
    currentTeamSlug: string;
    startDate: string;
    endDate: string;
}>();

const emit = defineEmits<{
    setActiveComponentId: [id: number];
    startDragMove: [component: ComponentItem, event: PointerEvent];
    startCornerResize: [component: ComponentItem, event: PointerEvent];
    saveSettings: [
        payload: { component: ComponentItem; settings: Record<string, any> },
    ];
    deleteComponent: [component: ComponentItem];
}>();

const canvasRef = useTemplateRef<HTMLElement>('canvasRef');

defineExpose({
    canvasRef,
});
</script>

<template>
    <main class="min-w-0">
        <!-- Empty State -->
        <div
            v-if="localComponents.length === 0"
            class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 p-12 text-center dark:border-neutral-800"
        >
            <Grid class="mb-2 size-10 text-muted-foreground opacity-50" />
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                Este dashboard está vazio
            </h3>
            <p class="mt-1 text-xs text-muted-foreground">
                Adicione um indicador, gráfico ou texto para posicionar na
                grade.
            </p>
        </div>

        <!-- Canvas Area -->
        <div
            v-else
            ref="canvasRef"
            :style="{ height: canvasHeight }"
            data-dashboard-canvas
            class="relative w-full min-w-0 touch-pan-y overflow-hidden rounded-2xl border border-slate-200/80 bg-white bg-[radial-gradient(#e2e8f0_1px,transparent_1px)] [background-size:20px_20px] transition-[height] dark:border-neutral-800 dark:bg-neutral-900 dark:bg-[radial-gradient(#262626_1px,transparent_1px)]"
        >
            <!-- Ghost Rect During Dragging -->
            <div
                v-if="dragOriginRect"
                :style="{
                    left: `${dragOriginRect.x}px`,
                    top: `${dragOriginRect.y}px`,
                    width: `${dragOriginRect.w}px`,
                    height: `${dragOriginRect.h}px`,
                }"
                class="pointer-events-none absolute z-20 rounded-xl border-2 border-dashed border-primary/50 bg-primary/5"
                aria-hidden="true"
            />

            <!-- Component Items in Grid -->
            <div
                v-for="comp in orderedComponents"
                :key="comp.id"
                @pointerdown="emit('setActiveComponentId', comp.id)"
                :style="{
                    position: 'absolute',
                    left: `${draggingComponent?.id === comp.id && dragPreview ? dragPreview.x : componentRect(comp).x}px`,
                    top: `${draggingComponent?.id === comp.id && dragPreview ? dragPreview.y : componentRect(comp).y}px`,
                    width: `${draggingComponent?.id === comp.id && dragPreview ? dragPreview.w : componentRect(comp).w}px`,
                    height: `${draggingComponent?.id === comp.id && dragPreview ? dragPreview.h : componentRect(comp).h}px`,
                    zIndex: activeComponentId === comp.id ? 40 : 10,
                }"
                :data-dashboard-component="comp.id"
                class="group max-w-full"
                :class="{
                    'pointer-events-none opacity-90 drop-shadow-2xl':
                        draggingComponent?.id === comp.id,
                    'rounded-xl ring-2 ring-primary ring-offset-4 ring-offset-white dark:ring-offset-neutral-900':
                        dropTargetId === comp.id,
                    'transition-[left,top,width,height] duration-200 ease-out':
                        !isInteracting,
                    'ring-2 ring-red-500':
                        resizingComponent?.id === comp.id && resizeHasCollision,
                }"
            >
                <div
                    v-if="dropTargetId === comp.id"
                    class="pointer-events-none absolute inset-0 z-[60] flex items-center justify-center rounded-xl bg-primary/10"
                    aria-hidden="true"
                >
                    <span
                        class="rounded-full bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-lg"
                    >
                        Solte para trocar
                    </span>
                </div>

                <!-- Component Item Box -->
                <DashboardComponentItem
                    :component="comp"
                    :current-team-slug="currentTeamSlug"
                    :start-date="startDate"
                    :end-date="endDate"
                    @drag-start="emit('startDragMove', comp, $event)"
                    @save-settings="(payload) => emit('saveSettings', payload)"
                    @delete="emit('deleteComponent', comp)"
                />

                <!-- Grid-snapped corner resize handle -->
                <div
                    v-if="!isCompactCanvas"
                    @pointerdown="emit('startCornerResize', comp, $event)"
                    title="Arrastar para redimensionar na grade"
                    class="absolute right-1 bottom-1 z-50 flex size-7 cursor-se-resize touch-none items-center justify-center rounded-tl-md rounded-br-lg bg-slate-200/90 text-slate-600 opacity-100 transition-opacity hover:bg-primary hover:text-white sm:size-5 sm:opacity-0 sm:group-hover:opacity-100 dark:bg-neutral-800/90 dark:text-neutral-400"
                >
                    <svg class="size-4" viewBox="0 0 8 8" fill="currentColor">
                        <circle cx="7" cy="7" r="1" />
                        <circle cx="7" cy="4" r="1" />
                        <circle cx="4" cy="7" r="1" />
                        <circle cx="7" cy="1" r="1" />
                        <circle cx="1" cy="7" r="1" />
                    </svg>
                </div>
            </div>

            <p class="sr-only" aria-live="polite">
                {{
                    dropTargetId
                        ? 'Área de soltura selecionada. Solte para trocar os componentes.'
                        : draggingComponent
                          ? 'Arraste até cobrir pelo menos metade de outro componente.'
                          : ''
                }}
            </p>
        </div>
    </main>
</template>
