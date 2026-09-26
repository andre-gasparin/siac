<?php

namespace App\Features\Tasks\Http\Controllers;

use App\Features\Tasks\Events\TaskSavedEvent;
use App\Features\Tasks\Http\Requests\StoreChecklistRequest;
use App\Features\Tasks\Http\Requests\UpdateChecklistRequest;
use App\Features\Tasks\Services\TaskBoardService;
use App\Http\Controllers\Controller;
use App\Models\KanbanBoard;
use App\Models\KanbanTask;
use App\Models\KanbanTaskChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskChecklistController extends Controller
{
    public function __construct(
        protected TaskBoardService $service
    ) {}

    /**
     * Store a checklist item.
     */
    public function store(StoreChecklistRequest $request, KanbanBoard $board, KanbanTask $task): RedirectResponse|JsonResponse
    {
        $maxOrder = (int) $task->checklists()->max('order');

        $checklist = $task->checklists()->create([
            'title' => $request->validated('title'),
            'is_completed' => false,
            'order' => $maxOrder + 1,
        ]);

        $formattedTask = $this->service->formatTask($task->fresh());

        broadcast(new TaskSavedEvent($board->id, $formattedTask, isNew: false))->toOthers();

        if ($request->wantsJson()) {
            return response()->json([
                'checklist' => $checklist,
                'task' => $formattedTask,
            ], 201);
        }

        return back()->with('success', 'Item adicionado ao checklist.');
    }

    /**
     * Update a checklist item.
     */
    public function update(UpdateChecklistRequest $request, KanbanBoard $board, KanbanTask $task, KanbanTaskChecklist $checklist): RedirectResponse|JsonResponse
    {
        $checklist->update($request->validated());

        $formattedTask = $this->service->formatTask($task->fresh());

        broadcast(new TaskSavedEvent($board->id, $formattedTask, isNew: false))->toOthers();

        if ($request->wantsJson()) {
            return response()->json([
                'checklist' => $checklist,
                'task' => $formattedTask,
            ]);
        }

        return back()->with('success', 'Checklist atualizado.');
    }

    /**
     * Delete a checklist item.
     */
    public function destroy(Request $request, KanbanBoard $board, KanbanTask $task, KanbanTaskChecklist $checklist): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $checklist->delete();

        $formattedTask = $this->service->formatTask($task->fresh());

        broadcast(new TaskSavedEvent($board->id, $formattedTask, isNew: false))->toOthers();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'task' => $formattedTask,
            ]);
        }

        return back()->with('success', 'Item removido do checklist.');
    }
}
