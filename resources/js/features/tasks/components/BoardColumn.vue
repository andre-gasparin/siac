<script setup lang="ts">
import {
    GripVertical,
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { nextTick, ref } from 'vue';
import { Button } from '@/shared/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu';
import { Input } from '@/shared/components/ui/input';
import type { Column, Task } from '../types';
import TaskCard from './TaskCard.vue';

const props = defineProps<{
    boardId: number;
    column: Column;
}>();

const emit = defineEmits<{
    (e: 'task-click', task: Task): void;
    (e: 'task-toggle-completed', task: Task): void;
    (e: 'edit-column', column: Column): void;
    (e: 'delete-column', column: Column): void;
    (e: 'create-task', payload: { columnId: number; title: string }): void;
}>();

const isAddingTask = ref(false);
const newTaskTitle = ref('');
const inputRef = ref<HTMLInputElement | null>(null);
const cardsContainerRef = ref<HTMLElement | null>(null);

defineExpose({
    cardsContainerRef,
});

async function startAddingTask() {
    isAddingTask.value = true;
    newTaskTitle.value = '';
    await nextTick();
    inputRef.value?.focus();
}

function cancelAddingTask() {
    isAddingTask.value = false;
    newTaskTitle.value = '';
}

function handleCreateTask() {
    const title = newTaskTitle.value.trim();

    if (!title) {
        return;
    }

    emit('create-task', {
        columnId: props.column.id,
        title,
    });

    newTaskTitle.value = '';
    // keep input active for quick consecutive additions
    inputRef.value?.focus();
}
</script>

<template>
    <div
        :data-column-id="column.id"
        class="column-wrapper flex h-full max-h-full w-80 shrink-0 flex-col rounded-xl border border-border/80 bg-muted/40 p-3 shadow-xs select-none"
    >
        <!-- Column Header -->
        <div
            class="column-header mb-2.5 flex items-center justify-between gap-2 px-1"
        >
            <div class="flex items-center gap-2 overflow-hidden">
                <span
                    class="h-3 w-3 shrink-0 rounded-full"
                    :style="{ backgroundColor: column.color || '#64748b' }"
                ></span>
                <h3 class="truncate text-sm font-semibold text-foreground">
                    {{ column.name }}
                </h3>
                <span
                    class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-muted px-1.5 text-[11px] font-medium text-muted-foreground"
                >
                    {{ column.tasks.length }}
                </span>
            </div>

            <div class="flex items-center gap-1">
                <!-- Column Drag Handle -->
                <button
                    type="button"
                    class="column-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground active:cursor-grabbing"
                    title="Arrastar coluna"
                >
                    <GripVertical class="h-4 w-4" />
                </button>

                <!-- Column Menu Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 text-muted-foreground"
                        >
                            <MoreHorizontal class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem @click="emit('edit-column', column)">
                            <Pencil class="mr-2 h-4 w-4" />
                            Editar Coluna
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @click="emit('delete-column', column)"
                            class="text-destructive focus:text-destructive"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Excluir Coluna
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Cards List Area -->
        <div class="relative flex min-h-[60px] flex-1 flex-col overflow-hidden">
            <!-- Cards List (Scrollable & Sortable drop container) -->
            <div
                ref="cardsContainerRef"
                :data-column-id="column.id"
                class="cards-container min-h-full flex-1 space-y-2.5 overflow-y-auto px-0.5 py-1"
            >
                <TaskCard
                    v-for="task in column.tasks"
                    :key="task.id"
                    :task="task"
                    @click="emit('task-click', $event)"
                    @toggle-completed="emit('task-toggle-completed', $event)"
                />
            </div>

            <!-- Empty Column Notice (Informational overlay only, pointer-events-none, never interferes with drag) -->
            <div
                v-if="column.tasks.length === 0 && !isAddingTask"
                class="empty-notice pointer-events-none absolute inset-x-0.5 top-1 flex h-20 items-center justify-center rounded-lg border border-dashed border-border/70 text-xs text-muted-foreground/60 italic"
            >
                Nenhuma tarefa
            </div>
        </div>

        <!-- Add Task Quick Input / Button -->
        <div class="mt-2 border-t border-border/40 pt-1">
            <div v-if="isAddingTask" class="space-y-2">
                <Input
                    ref="inputRef"
                    v-model="newTaskTitle"
                    placeholder="Título da tarefa..."
                    class="bg-background text-sm shadow-xs"
                    @keydown.enter.prevent="handleCreateTask"
                    @keydown.esc="cancelAddingTask"
                />
                <div class="flex items-center gap-1.5">
                    <Button
                        size="sm"
                        class="h-7 px-3 text-xs"
                        :disabled="!newTaskTitle.trim()"
                        @click="handleCreateTask"
                    >
                        Adicionar
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7 text-muted-foreground"
                        @click="cancelAddingTask"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <Button
                v-else
                variant="ghost"
                class="w-full justify-start text-xs text-muted-foreground hover:bg-background hover:text-foreground"
                @click="startAddingTask"
            >
                <Plus class="mr-1.5 h-3.5 w-3.5" />
                Adicionar Tarefa
            </Button>
        </div>
    </div>
</template>

<style scoped>
.cards-container:has(> *) ~ .empty-notice {
    display: none;
}
</style>
