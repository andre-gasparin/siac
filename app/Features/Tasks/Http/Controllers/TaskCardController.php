<?php

namespace App\Features\Tasks\Http\Controllers;

use App\Features\Tasks\Events\TaskDeletedEvent;
use App\Features\Tasks\Events\TaskMovedEvent;
use App\Features\Tasks\Events\TaskSavedEvent;
use App\Features\Tasks\Http\Requests\MoveTaskRequest;
use App\Features\Tasks\Http\Requests\StoreTaskRequest;
use App\Features\Tasks\Http\Requests\UpdateTaskRequest;
use App\Features\Tasks\Services\TaskBoardService;
use App\Http\Controllers\Controller;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCardController extends Controller
{
    public function __construct(
        protected TaskBoardService $service
    ) {}

    /**
     * Store a new task in a column.
     */
    public function store(StoreTaskRequest $request, KanbanBoard $board, KanbanColumn $column): RedirectResponse|JsonResponse
    {
        $maxOrder = (int) $column->tasks()->max('order');

        $task = KanbanTask::create([
            'kanban_board_id' => $board->id,
            'kanban_column_id' => $column->id,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'due_date' => $request->validated('due_date'),
            'assigned_to_user_id' => $request->validated('assigned_to_user_id'),
            'created_by' => $request->user()->id,
            'order' => $maxOrder + 1,
        ]);

        if ($request->has('checklists') && is_array($request->input('checklists'))) {
            foreach ($request->input('checklists') as $index => $item) {
                if (! empty($item['title'])) {
                    $task->checklists()->create([
                        'title' => $item['title'],
                        'order' => $index,
                        'is_completed' => false,
                    ]);
                }
            }
        }

        $formatted = $this->service->formatTask($task->fresh());

        broadcast(new TaskSavedEvent($board->id, $formatted, isNew: true))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['task' => $formatted], 201);
        }

        return back()->with('success', 'Tarefa criada com sucesso.');
    }

    /**
     * Update an existing task.
     */
    public function update(UpdateTaskRequest $request, KanbanBoard $board, KanbanTask $task): RedirectResponse|JsonResponse
    {
        $task->update($request->validated());

        $formatted = $this->service->formatTask($task->fresh());

        broadcast(new TaskSavedEvent($board->id, $formatted, isNew: false))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['task' => $formatted]);
        }

        return back()->with('success', 'Tarefa atualizada com sucesso.');
    }

    /**
     * Toggle completion status.
     */
    public function toggleCompleted(Request $request, KanbanBoard $board, KanbanTask $task): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $task->is_completed = ! $task->is_completed;
        $task->completed_at = $task->is_completed ? now() : null;
        $task->save();

        $formatted = $this->service->formatTask($task->fresh());

        broadcast(new TaskSavedEvent($board->id, $formatted, isNew: false))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['task' => $formatted]);
        }

        return back()->with('success', 'Status da tarefa atualizado.');
    }

    /**
     * Move task to a new column or position.
     */
    public function move(MoveTaskRequest $request, KanbanBoard $board, KanbanTask $task): JsonResponse
    {
        $fromColumnId = $task->kanban_column_id;
        $toColumnId = (int) $request->validated('to_column_id');
        $newOrder = (int) $request->validated('new_order');
        $reorderedTaskIds = $request->validated('reordered_task_ids') ?? [];

        $task->kanban_column_id = $toColumnId;
        $task->order = $newOrder;
        $task->save();

        if (! empty($reorderedTaskIds)) {
            foreach ($reorderedTaskIds as $index => $id) {
                KanbanTask::where('id', $id)->update([
                    'order' => $index,
                    'kanban_column_id' => $toColumnId,
                ]);
            }
        }

        broadcast(new TaskMovedEvent(
            $board->id,
            $task->id,
            $fromColumnId,
            $toColumnId,
            $newOrder,
            $reorderedTaskIds
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Delete a task.
     */
    public function destroy(Request $request, KanbanBoard $board, KanbanTask $task): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $taskId = $task->id;
        $columnId = $task->kanban_column_id;
        $task->delete();

        broadcast(new TaskDeletedEvent($board->id, $taskId, $columnId))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Tarefa excluída com sucesso.');
    }
}
