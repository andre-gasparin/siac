<script setup lang="ts">
import {
    AlertCircle,
    Calendar,
    Check,
    CheckSquare,
    Paperclip,
} from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback } from '@/shared/components/ui/avatar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/shared/components/ui/tooltip';
import type { Task } from '../types';

const props = defineProps<{
    task: Task;
}>();

const emit = defineEmits<{
    (e: 'click', task: Task): void;
    (e: 'toggle-completed', task: Task): void;
}>();

const dueDateInfo = computed(() => {
    if (!props.task.due_date) {
        return null;
    }

    const parts = props.task.due_date.split('-');

    if (parts.length !== 3) {
        return null;
    }

    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const day = parseInt(parts[2], 10);
    const dueDate = new Date(year, month, day, 23, 59, 59);

    const now = new Date();
    const today = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
        23,
        59,
        59,
    );

    const diffDays = Math.round(
        (dueDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );

    const formatted = `${String(day).padStart(2, '0')}/${String(month + 1).padStart(2, '0')}`;

    let status: 'overdue' | 'today' | 'upcoming' | 'normal' = 'normal';

    if (props.task.is_completed) {
        status = 'normal';
    } else if (diffDays < 0) {
        status = 'overdue';
    } else if (diffDays === 0) {
        status = 'today';
    } else if (diffDays === 1) {
        status = 'upcoming';
    }

    return {
        formatted,
        diffDays,
        status,
    };
});

const initials = computed(() => {
    if (!props.task.assigned_user?.name) {
        return '?';
    }

    const words = props.task.assigned_user.name.trim().split(/\s+/);

    if (words.length === 1) {
        return words[0].substring(0, 2).toUpperCase();
    }

    return (words[0][0] + words[words.length - 1][0]).toUpperCase();
});

const checklistProgress = computed(() => {
    if (!props.task.checklists_count) {
        return 0;
    }

    return Math.round(
        (props.task.completed_checklists_count / props.task.checklists_count) *
            100,
    );
});

function handleToggleCheckbox(e: Event) {
    e.stopPropagation();
    emit('toggle-completed', props.task);
}
</script>

<template>
    <div
        :data-task-id="task.id"
        @click="emit('click', task)"
        class="task-card group relative flex cursor-pointer flex-col gap-2.5 rounded-lg border bg-card p-3 shadow-xs transition-all select-none hover:border-primary/50 hover:shadow-md"
        :class="{
            'border-border/60 bg-muted/30 opacity-75': task.is_completed,
            'border-border': !task.is_completed,
        }"
    >
        <!-- Top Row: Completion Checkbox & Title -->
        <div class="flex items-start gap-2.5">
            <button
                type="button"
                @click="handleToggleCheckbox"
                class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border transition-colors focus:outline-none"
                :class="{
                    'border-emerald-600 bg-emerald-600 text-white dark:border-emerald-500 dark:bg-emerald-500':
                        task.is_completed,
                    'border-muted-foreground/40 hover:border-emerald-500 hover:bg-emerald-500/10':
                        !task.is_completed,
                }"
                :title="
                    task.is_completed
                        ? 'Marcar como não concluída'
                        : 'Marcar como concluída'
                "
            >
                <Check v-if="task.is_completed" class="h-3 w-3 stroke-[3]" />
            </button>

            <span
                class="flex-1 text-sm leading-snug font-medium break-words transition-all"
                :class="{
                    'text-muted-foreground line-through': task.is_completed,
                    'text-card-foreground group-hover:text-primary':
                        !task.is_completed,
                }"
            >
                {{ task.title }}
            </span>
        </div>

        <!-- Description Snippet (if any) -->
        <p
            v-if="task.description"
            class="line-clamp-2 text-xs leading-relaxed text-muted-foreground/80"
            :class="{ 'opacity-60': task.is_completed }"
        >
            {{ task.description }}
        </p>

        <!-- Checklist Mini Progress Bar (if checklists exist) -->
        <div
            v-if="task.checklists_count > 0"
            class="flex flex-col gap-1 pt-0.5"
        >
            <div
                class="flex items-center justify-between text-[11px] font-medium text-muted-foreground"
            >
                <span class="flex items-center gap-1">
                    <CheckSquare class="h-3 w-3" />
                    Checklist
                </span>
                <span
                    >{{ task.completed_checklists_count }}/{{
                        task.checklists_count
                    }}</span
                >
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="
                        checklistProgress === 100
                            ? 'bg-emerald-500'
                            : 'bg-primary/80'
                    "
                    :style="{ width: `${checklistProgress}%` }"
                ></div>
            </div>
        </div>

        <!-- Card Footer: Due Date, Attachments, Assigned User -->
        <div
            class="mt-1 flex items-center justify-between border-t border-border/40 pt-1 text-xs"
        >
            <div class="flex flex-wrap items-center gap-2">
                <!-- Due Date Badge -->
                <div
                    v-if="dueDateInfo"
                    class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] font-medium"
                    :class="{
                        'border-destructive/30 bg-destructive/10 text-destructive':
                            dueDateInfo.status === 'overdue',
                        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400':
                            dueDateInfo.status === 'today' ||
                            dueDateInfo.status === 'upcoming',
                        'border-border/60 bg-muted/50 text-muted-foreground':
                            dueDateInfo.status === 'normal',
                    }"
                >
                    <AlertCircle
                        v-if="dueDateInfo.status === 'overdue'"
                        class="h-3 w-3 shrink-0"
                    />
                    <Calendar v-else class="h-3 w-3 shrink-0" />
                    <span>{{ dueDateInfo.formatted }}</span>
                </div>

                <!-- Attachments Badge -->
                <div
                    v-if="task.attachments_count > 0"
                    class="inline-flex items-center gap-1 text-[11px] text-muted-foreground"
                    title="Anexos"
                >
                    <Paperclip class="h-3 w-3" />
                    <span>{{ task.attachments_count }}</span>
                </div>
            </div>

            <!-- Assigned User Avatar -->
            <TooltipProvider v-if="task.assigned_user">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Avatar
                            class="h-6 w-6 border border-border text-[10px] font-semibold"
                        >
                            <AvatarFallback class="bg-primary/10 text-primary">
                                {{ initials }}
                            </AvatarFallback>
                        </Avatar>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-xs">{{ task.assigned_user.name }}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
    </div>
</template>
