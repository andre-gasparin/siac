<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, FolderKanban, Pencil, Plus, Trash2 } from '@lucide/vue';
import {
    destroy as destroyBoardRoute,
    show as showBoardRoute,
} from '@/routes/tasks/boards';
import { Avatar, AvatarFallback } from '@/shared/components/ui/avatar';
import { Button } from '@/shared/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/shared/components/ui/tooltip';
import type { Board, BoardSummary, OnlineUser } from '../types';

const props = defineProps<{
    board: Board;
    boards: BoardSummary[];
    onlineUsers: OnlineUser[];
}>();

const emit = defineEmits<{
    (e: 'new-board'): void;
    (e: 'edit-board'): void;
    (e: 'new-column'): void;
}>();

function switchBoard(targetBoardId: number) {
    if (targetBoardId === props.board.id) {
        return;
    }

    router.visit(showBoardRoute(targetBoardId).url);
}

function handleDeleteBoard() {
    if (props.boards.length <= 1) {
        alert('Não é possível excluir o único quadro existente.');

        return;
    }

    if (
        confirm(
            `Tem certeza de que deseja excluir permanentemente o quadro "${props.board.name}" e todas as suas tarefas?`,
        )
    ) {
        router.delete(destroyBoardRoute(props.board.id).url);
    }
}

function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/);

    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}
</script>

<template>
    <div
        class="flex flex-col gap-3 border-b border-border/80 bg-background/95 px-6 py-3.5 backdrop-blur-xs sm:flex-row sm:items-center sm:justify-between"
    >
        <!-- Left: Board Switcher & Title -->
        <div class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
                <FolderKanban class="h-5 w-5" />
            </div>

            <div>
                <div class="flex items-center gap-1.5">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="group flex items-center gap-1.5 text-lg font-bold text-foreground hover:text-primary focus:outline-none"
                            >
                                <span>{{ board.name }}</span>
                                <ChevronDown
                                    class="h-4 w-4 text-muted-foreground transition-transform group-hover:text-primary"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-56">
                            <div
                                class="px-2 py-1.5 text-xs font-semibold text-muted-foreground"
                            >
                                Alternar Quadro
                            </div>
                            <DropdownMenuItem
                                v-for="b in boards"
                                :key="b.id"
                                :class="{
                                    'font-semibold text-primary':
                                        b.id === board.id,
                                }"
                                @click="switchBoard(b.id)"
                            >
                                <span class="truncate">{{ b.name }}</span>
                            </DropdownMenuItem>

                            <DropdownMenuSeparator />

                            <DropdownMenuItem @click="emit('new-board')">
                                <Plus class="mr-2 h-4 w-4" />
                                Novo Quadro...
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="emit('edit-board')">
                                <Pencil class="mr-2 h-4 w-4" />
                                Editar Este Quadro
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="boards.length > 1"
                                @click="handleDeleteBoard"
                                class="text-destructive focus:text-destructive"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Excluir Este Quadro
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <p
                    v-if="board.description"
                    class="line-clamp-1 text-xs text-muted-foreground"
                >
                    {{ board.description }}
                </p>
            </div>
        </div>

        <!-- Right: Online Presence Avatars & Actions -->
        <div class="flex items-center gap-3">
            <!-- Real-time Presence Avatars -->
            <div
                v-if="onlineUsers.length > 0"
                class="flex items-center gap-1.5 rounded-full border border-border/80 bg-muted/40 px-2.5 py-1 text-xs"
            >
                <span class="relative flex h-2 w-2">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"
                    ></span>
                </span>

                <div class="flex -space-x-1.5 overflow-hidden pl-1">
                    <TooltipProvider v-for="user in onlineUsers" :key="user.id">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Avatar
                                    class="h-6 w-6 border-2 border-background text-[10px] font-semibold"
                                >
                                    <AvatarFallback
                                        class="bg-primary/20 text-primary"
                                    >
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p class="text-xs">{{ user.name }} (online)</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>

            <!-- Add Column Button -->
            <Button
                size="sm"
                class="gap-1.5 text-xs shadow-xs"
                @click="emit('new-column')"
            >
                <Plus class="h-4 w-4" />
                Nova Coluna
            </Button>
        </div>
    </div>
</template>
