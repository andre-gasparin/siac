<?php

namespace App\Features\Tasks\Services;

use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanTask;
use App\Models\User;

class TaskBoardService
{
    /**
     * Ensure at least one default board exists.
     */
    public function ensureDefaultBoard(?User $user = null): KanbanBoard
    {
        $board = KanbanBoard::query()->orderBy('order')->first();

        if ($board) {
            return $board;
        }

        $board = KanbanBoard::create([
            'name' => 'Quadro Principal',
            'description' => 'Quadro de tarefas geral da administração',
            'order' => 0,
            'created_by' => $user?->id,
        ]);

        $defaultColumns = [
            ['name' => 'A Fazer', 'color' => '#64748b', 'order' => 0],
            ['name' => 'Em Andamento', 'color' => '#3b82f6', 'order' => 1],
            ['name' => 'Concluído', 'color' => '#10b981', 'order' => 2],
        ];

        foreach ($defaultColumns as $col) {
            $board->columns()->create($col);
        }

        return $board;
    }

    /**
     * Format a complete board for Inertia props or responses.
     *
     * @return array<string, mixed>
     */
    public function formatBoard(KanbanBoard $board): array
    {
        $board->load([
            'columns' => fn ($query) => $query->orderBy('order'),
            'columns.tasks' => fn ($query) => $query->orderBy('order'),
            'columns.tasks.assignedUser:id,name,email',
            'columns.tasks.checklists' => fn ($query) => $query->orderBy('order'),
            'columns.tasks.attachments' => fn ($query) => $query->latest(),
            'columns.tasks.attachments.user:id,name',
        ]);

        return [
            'id' => $board->id,
            'name' => $board->name,
            'description' => $board->description,
            'order' => $board->order,
            'created_by' => $board->created_by,
            'columns' => $board->columns->map(fn (KanbanColumn $col) => $this->formatColumn($col))->values()->all(),
        ];
    }

    /**
     * Format a column with its tasks.
     *
     * @return array<string, mixed>
     */
    public function formatColumn(KanbanColumn $column): array
    {
        return [
            'id' => $column->id,
            'kanban_board_id' => $column->kanban_board_id,
            'name' => $column->name,
            'color' => $column->color,
            'order' => $column->order,
            'tasks' => $column->relationLoaded('tasks')
                ? $column->tasks->map(fn (KanbanTask $t) => $this->formatTask($t))->values()->all()
                : [],
        ];
    }

    /**
     * Format a single task.
     *
     * @return array<string, mixed>
     */
    public function formatTask(KanbanTask $task): array
    {
        $checklists = $task->relationLoaded('checklists') ? $task->checklists : $task->checklists()->orderBy('order')->get();
        $attachments = $task->relationLoaded('attachments') ? $task->attachments : $task->attachments()->latest()->with('user:id,name')->get();
        $assignedUser = $task->relationLoaded('assignedUser') ? $task->assignedUser : $task->assignedUser;

        return [
            'id' => $task->id,
            'kanban_board_id' => $task->kanban_board_id,
            'kanban_column_id' => $task->kanban_column_id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'is_completed' => (bool) $task->is_completed,
            'completed_at' => $task->completed_at?->toIso8601String(),
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_user' => $assignedUser ? [
                'id' => $assignedUser->id,
                'name' => $assignedUser->name,
                'email' => $assignedUser->email,
            ] : null,
            'order' => $task->order,
            'created_at' => $task->created_at?->toIso8601String(),
            'checklists_count' => $checklists->count(),
            'completed_checklists_count' => $checklists->where('is_completed', true)->count(),
            'attachments_count' => $attachments->count(),
            'checklists' => $checklists->map(fn ($item) => [
                'id' => $item->id,
                'kanban_task_id' => $item->kanban_task_id,
                'title' => $item->title,
                'is_completed' => (bool) $item->is_completed,
                'order' => $item->order,
            ])->values()->all(),
            'attachments' => $attachments->map(fn ($att) => [
                'id' => $att->id,
                'kanban_task_id' => $att->kanban_task_id,
                'original_name' => $att->original_name,
                'mime_type' => $att->mime_type,
                'size_bytes' => $att->size_bytes,
                'created_at' => $att->created_at?->toIso8601String(),
                'user' => $att->user ? [
                    'id' => $att->user->id,
                    'name' => $att->user->name,
                ] : null,
            ])->values()->all(),
        ];
    }
}
