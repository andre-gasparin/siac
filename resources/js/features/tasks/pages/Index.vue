<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { echo } from '@laravel/echo-vue';
import Sortable from 'sortablejs';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    store as storeCardRoute,
    toggle as toggleCardRoute,
} from '@/routes/tasks/cards';
import {
    destroy as destroyColumnRoute,
    reorder as reorderColumnsRoute,
} from '@/routes/tasks/columns';
import BoardColumn from '../components/BoardColumn.vue';
import BoardHeader from '../components/BoardHeader.vue';
import NewBoardModal from '../components/NewBoardModal.vue';
import NewColumnModal from '../components/NewColumnModal.vue';
import TaskDrawer from '../components/TaskDrawer.vue';
import type {
    Board,
    BoardSummary,
    Column,
    OnlineUser,
    Task,
    TaskUser,
} from '../types';

const props = defineProps<{
    board: Board;
    boards: BoardSummary[];
    administrators: TaskUser[];
}>();

const currentBoard = ref<Board>(JSON.parse(JSON.stringify(props.board)));
const onlineUsers = ref<OnlineUser[]>([]);

// Modals and Drawer state
const isNewBoardModalOpen = ref(false);
const boardToEdit = ref<Board | null>(null);

const isNewColumnModalOpen = ref(false);
const columnToEdit = ref<Column | null>(null);

const activeTask = ref<Task | null>(null);
const isDrawerOpen = ref(false);

const columnsContainerRef = ref<HTMLElement | null>(null);
const sortableInstances: Sortable[] = [];

// Watch props.board changes (e.g. switching board)
watch(
    () => props.board,
    (newBoard) => {
        currentBoard.value = JSON.parse(JSON.stringify(newBoard));
        nextTick(() => {
            initSortables();
        });
    },
    { deep: true },
);

const activeColumnName = computed(() => {
    if (!activeTask.value) {
        return undefined;
    }

    const col = currentBoard.value.columns.find(
        (c) => c.id === activeTask.value?.kanban_column_id,
    );

    return col?.name;
});

function getXsrfToken(): string {
    const match = document.cookie.match(
        new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'),
    );

    return match ? decodeURIComponent(match[2]) : '';
}

// ==========================================
// SortableJS Drag & Drop Initialization
// ==========================================
function initSortables() {
    // Destroy previous sortable instances
    sortableInstances.forEach((inst) => inst.destroy());
    sortableInstances.length = 0;

    // 1. Column Drag-and-Drop (horizontal reorder)
    if (columnsContainerRef.value) {
        const colSortable = Sortable.create(columnsContainerRef.value, {
            handle: '.column-drag-handle',
            animation: 200,
            draggable: '.column-wrapper',
            ghostClass: 'opacity-40',
            onEnd: async () => {
                if (!columnsContainerRef.value) {
                    return;
                }

                const columnEls =
                    columnsContainerRef.value.querySelectorAll(
                        '.column-wrapper',
                    );
                const orderedIds = Array.from(columnEls)
                    .map((el) => Number(el.getAttribute('data-column-id')))
                    .filter(Boolean);

                try {
                    const url = reorderColumnsRoute(currentBoard.value.id).url;
                    await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-XSRF-TOKEN': getXsrfToken(),
                        },
                        body: JSON.stringify({
                            ordered_column_ids: orderedIds,
                        }),
                    });
                } catch {
                    toast.error('Erro ao reordenar colunas.');
                }
            },
        });
        sortableInstances.push(colSortable);
    }

    // 2. Card Drag-and-Drop across all columns
    if (columnsContainerRef.value) {
        const cardContainers =
            columnsContainerRef.value.querySelectorAll('.cards-container');
        cardContainers.forEach((container) => {
            const cardSortable = Sortable.create(container as HTMLElement, {
                group: 'kanban-cards',
                animation: 150,
                ghostClass: 'opacity-30',
                chosenClass: 'scale-[1.02]',
                dragClass: 'shadow-lg',
                emptyInsertThreshold: 15,
                draggable: '.task-card',
                onEnd: async (evt) => {
                    const itemEl = evt.item;
                    const toContainer = evt.to;
                    const fromContainer = evt.from;
                    const taskId = Number(itemEl.getAttribute('data-task-id'));
                    const fromColumnId = Number(
                        fromContainer.getAttribute('data-column-id'),
                    );
                    const toColumnId = Number(
                        toContainer.getAttribute('data-column-id'),
                    );
                    const newIndex = evt.newIndex ?? 0;

                    if (!taskId || !toColumnId) {
                        return;
                    }

                    // Collect all task IDs in destination column
                    const taskEls =
                        toContainer.querySelectorAll('[data-task-id]');
                    const reorderedTaskIds = Array.from(taskEls)
                        .map((el) => Number(el.getAttribute('data-task-id')))
                        .filter(Boolean);

                    handleRemoteTaskMoved({
                        taskId,
                        fromColumnId,
                        toColumnId,
                        newOrder: newIndex,
                        reorderedTaskIds,
                    });
                    await nextTick();
                    initSortables();

                    try {
                        const url = `/tarefas/${currentBoard.value.id}/tarefas/${taskId}/mover`;
                        await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-XSRF-TOKEN': getXsrfToken(),
                            },
                            body: JSON.stringify({
                                to_column_id: toColumnId,
                                new_order: newIndex,
                                reordered_task_ids: reorderedTaskIds,
                            }),
                        });
                    } catch {
                        toast.error('Erro ao mover tarefa.');
                    }
                },
            });
            sortableInstances.push(cardSortable);
        });
    }
}

// ==========================================
// Echo Real-time WebSocket Listeners
// ==========================================
function setupEchoListeners() {
    try {
        const echoInstance = echo();

        if (!echoInstance) {
            return;
        }

        const channelName = `kanban.board.${props.board.id}`;

        echoInstance
            .join(channelName)
            .here((users: OnlineUser[]) => {
                onlineUsers.value = users;
            })
            .joining((user: OnlineUser) => {
                if (!onlineUsers.value.some((u) => u.id === user.id)) {
                    onlineUsers.value.push(user);
                }
            })
            .leaving((user: OnlineUser) => {
                onlineUsers.value = onlineUsers.value.filter(
                    (u) => u.id !== user.id,
                );
            })
            .listen(
                '.TaskSaved',
                (e: { boardId: number; task: Task; isNew: boolean }) => {
                    handleRemoteTaskSaved(e.task);
                },
            )
            .listen(
                '.TaskMoved',
                (e: {
                    boardId: number;
                    taskId: number;
                    fromColumnId: number;
                    toColumnId: number;
                    newOrder: number;
                    reorderedTaskIds: number[];
                }) => {
                    handleRemoteTaskMoved(e);
                },
            )
            .listen(
                '.TaskDeleted',
                (e: { boardId: number; taskId: number; columnId: number }) => {
                    handleRemoteTaskDeleted(e.taskId, e.columnId);
                },
            )
            .listen(
                '.ColumnSaved',
                (e: { boardId: number; column: Column; isNew: boolean }) => {
                    handleRemoteColumnSaved(e.column, e.isNew);
                },
            )
            .listen(
                '.ColumnDeleted',
                (e: { boardId: number; columnId: number }) => {
                    handleRemoteColumnDeleted(e.columnId);
                },
            )
            .listen(
                '.ColumnsReordered',
                (e: { boardId: number; orderedColumnIds: number[] }) => {
                    handleRemoteColumnsReordered(e.orderedColumnIds);
                },
            )
            .listen(
                '.BoardUpdated',
                (e: {
                    boardId: number;
                    board: { name: string; description?: string };
                }) => {
                    currentBoard.value.name = e.board.name;

                    if (e.board.description !== undefined) {
                        currentBoard.value.description = e.board.description;
                    }
                },
            );
    } catch (err) {
        console.warn(
            'Echo not connected or WebSocket server not reachable:',
            err,
        );
    }
}

function handleRemoteTaskSaved(task: Task) {
    currentBoard.value.columns.forEach((col) => {
        const idx = col.tasks.findIndex((t) => t.id === task.id);

        if (idx !== -1) {
            if (col.id === task.kanban_column_id) {
                col.tasks[idx] = task;
            } else {
                col.tasks.splice(idx, 1);
            }
        }
    });

    const targetCol = currentBoard.value.columns.find(
        (c) => c.id === task.kanban_column_id,
    );

    if (targetCol) {
        const existingIdx = targetCol.tasks.findIndex((t) => t.id === task.id);

        if (existingIdx === -1) {
            targetCol.tasks.push(task);
            targetCol.tasks.sort((a, b) => a.order - b.order);
        }
    }

    if (activeTask.value && activeTask.value.id === task.id) {
        activeTask.value = task;
    }
}

function handleRemoteTaskMoved(e: {
    taskId: number;
    fromColumnId: number;
    toColumnId: number;
    newOrder: number;
    reorderedTaskIds: number[];
}) {
    let movedTask: Task | null = null;

    currentBoard.value.columns.forEach((col) => {
        const idx = col.tasks.findIndex((t) => t.id === e.taskId);

        if (idx !== -1) {
            movedTask = col.tasks.splice(idx, 1)[0];
        }
    });

    if (movedTask) {
        (movedTask as Task).kanban_column_id = e.toColumnId;
        (movedTask as Task).order = e.newOrder;

        const destCol = currentBoard.value.columns.find(
            (c) => c.id === e.toColumnId,
        );

        if (destCol) {
            destCol.tasks.splice(e.newOrder, 0, movedTask as Task);

            if (e.reorderedTaskIds && e.reorderedTaskIds.length) {
                destCol.tasks.sort(
                    (a, b) =>
                        e.reorderedTaskIds.indexOf(a.id) -
                        e.reorderedTaskIds.indexOf(b.id),
                );
            }
        }
    }
}

function handleRemoteTaskDeleted(taskId: number, columnId: number) {
    const col = currentBoard.value.columns.find((c) => c.id === columnId);

    if (col) {
        col.tasks = col.tasks.filter((t) => t.id !== taskId);
    }

    if (activeTask.value?.id === taskId) {
        isDrawerOpen.value = false;
        activeTask.value = null;
    }
}

function handleRemoteColumnSaved(column: Column, isNew: boolean) {
    if (isNew) {
        currentBoard.value.columns.push(column);
    } else {
        const idx = currentBoard.value.columns.findIndex(
            (c) => c.id === column.id,
        );

        if (idx !== -1) {
            currentBoard.value.columns[idx].name = column.name;
            currentBoard.value.columns[idx].color = column.color;
        }
    }

    nextTick(() => initSortables());
}

function handleRemoteColumnDeleted(columnId: number) {
    currentBoard.value.columns = currentBoard.value.columns.filter(
        (c) => c.id !== columnId,
    );
}

function handleRemoteColumnsReordered(orderedIds: number[]) {
    currentBoard.value.columns.sort(
        (a, b) => orderedIds.indexOf(a.id) - orderedIds.indexOf(b.id),
    );
}

// ==========================================
// User Actions
// ==========================================
function openTask(task: Task) {
    activeTask.value = task;
    isDrawerOpen.value = true;
}

async function handleToggleCompleted(task: Task) {
    try {
        const url = toggleCardRoute([currentBoard.value.id, task.id]).url;
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                handleRemoteTaskSaved(data.task);
            }
        }
    } catch {
        toast.error('Erro ao marcar tarefa como concluída.');
    }
}

async function handleCreateTask(payload: { columnId: number; title: string }) {
    try {
        const url = storeCardRoute([
            currentBoard.value.id,
            payload.columnId,
        ]).url;
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({ title: payload.title }),
        });

        if (res.ok) {
            const data = await res.json();

            if (data.task) {
                handleRemoteTaskSaved(data.task);
                nextTick(() => initSortables());
            }
        }
    } catch {
        toast.error('Erro ao adicionar tarefa.');
    }
}

function handleEditColumn(column: Column) {
    columnToEdit.value = column;
    isNewColumnModalOpen.value = true;
}

function handleDeleteColumn(column: Column) {
    if (column.tasks.length > 0) {
        if (
            !confirm(
                `Esta coluna possui ${column.tasks.length} tarefa(s). Deseja realmente excluí-la juntamente com as tarefas?`,
            )
        ) {
            return;
        }
    } else {
        if (!confirm(`Excluir a coluna "${column.name}"?`)) {
            return;
        }
    }

    router.delete(destroyColumnRoute([currentBoard.value.id, column.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            currentBoard.value.columns = currentBoard.value.columns.filter(
                (c) => c.id !== column.id,
            );
        },
    });
}

function openNewBoardModal() {
    boardToEdit.value = null;
    isNewBoardModalOpen.value = true;
}

function openEditBoardModal() {
    boardToEdit.value = currentBoard.value;
    isNewBoardModalOpen.value = true;
}

function openNewColumnModal() {
    columnToEdit.value = null;
    isNewColumnModalOpen.value = true;
}

function onTaskUpdatedInDrawer(updatedTask: Task) {
    handleRemoteTaskSaved(updatedTask);
}

function onTaskDeletedInDrawer(taskId: number) {
    currentBoard.value.columns.forEach((col) => {
        col.tasks = col.tasks.filter((t) => t.id !== taskId);
    });
}

onMounted(() => {
    initSortables();
    setupEchoListeners();
});

onUnmounted(() => {
    try {
        const echoInstance = echo();

        if (echoInstance) {
            echoInstance.leave(`kanban.board.${props.board.id}`);
        }
    } catch {}

    sortableInstances.forEach((inst) => inst.destroy());
});
</script>

<template>
    <Head :title="`${currentBoard.name} - Tarefas`" />

    <div
        class="flex h-[calc(100vh-4rem)] flex-col overflow-hidden bg-background"
    >
        <!-- Board Header (Switcher, Presence, Actions) -->
        <BoardHeader
            :board="currentBoard"
            :boards="boards"
            :online-users="onlineUsers"
            @new-board="openNewBoardModal"
            @edit-board="openEditBoardModal"
            @new-column="openNewColumnModal"
        />

        <!-- Columns Horizontal Scroll Area -->
        <div class="flex-1 overflow-x-auto overflow-y-hidden p-6">
            <div
                ref="columnsContainerRef"
                class="flex h-full items-start gap-4 pb-2"
            >
                <BoardColumn
                    v-for="column in currentBoard.columns"
                    :key="column.id"
                    :board-id="currentBoard.id"
                    :column="column"
                    @task-click="openTask"
                    @task-toggle-completed="handleToggleCompleted"
                    @edit-column="handleEditColumn"
                    @delete-column="handleDeleteColumn"
                    @create-task="handleCreateTask"
                />

                <!-- Add Column Placeholder Button -->
                <button
                    type="button"
                    @click="openNewColumnModal"
                    class="flex h-14 w-80 shrink-0 items-center justify-center gap-2 rounded-xl border border-dashed border-border/80 bg-muted/20 text-xs font-semibold text-muted-foreground transition-all hover:border-primary/50 hover:bg-muted/40 hover:text-foreground focus:outline-none"
                >
                    <span>+ Adicionar Coluna</span>
                </button>
            </div>
        </div>

        <!-- Modals and Drawer -->
        <NewBoardModal
            v-model:open="isNewBoardModalOpen"
            :board-to-edit="boardToEdit"
        />

        <NewColumnModal
            v-model:open="isNewColumnModalOpen"
            :board-id="currentBoard.id"
            :column-to-edit="columnToEdit"
        />

        <TaskDrawer
            v-model:open="isDrawerOpen"
            :board-id="currentBoard.id"
            :column-name="activeColumnName"
            :task="activeTask"
            :administrators="administrators"
            @task-updated="onTaskUpdatedInDrawer"
            @task-deleted="onTaskDeletedInDrawer"
        />
    </div>
</template>
