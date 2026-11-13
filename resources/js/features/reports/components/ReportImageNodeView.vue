<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import type { NodeViewProps } from '@tiptap/vue-3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps<NodeViewProps>();

const isHovered = ref(false);
const isResizing = ref(false);
const startX = ref(0);
const startWidth = ref(0);
const imageEl = ref<HTMLImageElement | null>(null);

const currentWidth = computed(() => {
    return props.node.attrs.width || '360px';
});

const presetSizes = ['25%', '50%', '75%', '100%'];

function setPresetWidth(size: string) {
    props.updateAttributes({ width: size });
}

function startResize(e: MouseEvent) {
    e.preventDefault();
    e.stopPropagation();

    isResizing.value = true;
    startX.value = e.clientX;
    startWidth.value = imageEl.value?.getBoundingClientRect().width || 360;

    window.addEventListener('mousemove', onResize);
    window.addEventListener('mouseup', stopResize);
}

function onResize(e: MouseEvent) {
    if (!isResizing.value) {
        return;
    }

    const deltaX = e.clientX - startX.value;
    const newWidth = Math.max(
        100,
        Math.min(1200, Math.round(startWidth.value + deltaX)),
    );

    props.updateAttributes({ width: `${newWidth}px` });
}

function stopResize() {
    isResizing.value = false;
    window.removeEventListener('mousemove', onResize);
    window.removeEventListener('mouseup', stopResize);
}

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onResize);
    window.removeEventListener('mouseup', stopResize);
});
</script>

<template>
    <NodeViewWrapper
        class="group relative my-3 inline-block max-w-full leading-none transition-all"
        :class="{
            'rounded-lg ring-2 ring-primary ring-offset-2':
                selected || isResizing,
        }"
        data-report-image-node
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <div class="relative inline-block max-w-full">
            <img
                ref="imageEl"
                :src="node.attrs.src"
                :alt="node.attrs.alt"
                :title="node.attrs.title"
                class="rounded-md object-contain shadow-xs transition-[width] duration-75"
                :style="{
                    width: currentWidth,
                    maxWidth: '100%',
                    height: 'auto',
                }"
            />

            <!-- Controles superiores de redimensionamento e exclusão -->
            <div
                v-if="selected || isHovered || isResizing"
                class="absolute -top-9 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1 rounded-md border border-border bg-popover/95 p-1 shadow-md backdrop-blur-xs select-none"
                data-test="image-controls"
                @pointerdown.stop
                @click.stop
            >
                <div
                    class="flex items-center gap-0.5 border-r border-border/80 pr-1"
                >
                    <button
                        v-for="size in presetSizes"
                        :key="size"
                        type="button"
                        class="rounded px-1.5 py-0.5 text-[11px] font-medium transition hover:bg-muted"
                        :class="
                            currentWidth === size
                                ? 'bg-primary font-semibold text-primary-foreground'
                                : 'text-muted-foreground'
                        "
                        @click="setPresetWidth(size)"
                    >
                        {{ size }}
                    </button>
                </div>

                <button
                    type="button"
                    class="rounded p-1 text-destructive hover:bg-destructive/10"
                    title="Excluir imagem"
                    @click="deleteNode()"
                >
                    <Trash2 class="size-3.5" />
                    <span class="sr-only">Excluir imagem</span>
                </button>
            </div>

            <!-- Alça interativa de arrasto no canto inferior direito -->
            <div
                v-if="selected || isHovered || isResizing"
                class="absolute -right-1.5 -bottom-1.5 z-20 size-4 cursor-se-resize rounded-full border-2 border-background bg-primary shadow-sm transition-transform hover:scale-125"
                title="Arrastar para redimensionar"
                @mousedown="startResize"
            />
        </div>
    </NodeViewWrapper>
</template>
