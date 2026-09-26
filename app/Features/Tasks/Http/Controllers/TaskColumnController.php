<?php

namespace App\Features\Tasks\Http\Controllers;

use App\Features\Tasks\Events\ColumnDeletedEvent;
use App\Features\Tasks\Events\ColumnSavedEvent;
use App\Features\Tasks\Events\ColumnsReorderedEvent;
use App\Features\Tasks\Http\Requests\ReorderColumnsRequest;
use App\Features\Tasks\Http\Requests\StoreColumnRequest;
use App\Features\Tasks\Http\Requests\UpdateColumnRequest;
use App\Features\Tasks\Services\TaskBoardService;
use App\Http\Controllers\Controller;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskColumnController extends Controller
{
    public function __construct(
        protected TaskBoardService $service
    ) {}

    /**
     * Store a new column in the board.
     */
    public function store(StoreColumnRequest $request, KanbanBoard $board): RedirectResponse|JsonResponse
    {
        $maxOrder = (int) $board->columns()->max('order');

        $column = $board->columns()->create([
            'name' => $request->validated('name'),
            'color' => $request->validated('color') ?? '#64748b',
            'order' => $maxOrder + 1,
        ]);

        $formatted = $this->service->formatColumn($column);

        broadcast(new ColumnSavedEvent($board->id, $formatted, isNew: true))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['column' => $formatted], 201);
        }

        return back()->with('success', 'Coluna criada com sucesso.');
    }

    /**
     * Update an existing column.
     */
    public function update(UpdateColumnRequest $request, KanbanBoard $board, KanbanColumn $column): RedirectResponse|JsonResponse
    {
        $column->update($request->validated());

        $formatted = $this->service->formatColumn($column);

        broadcast(new ColumnSavedEvent($board->id, $formatted, isNew: false))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['column' => $formatted]);
        }

        return back()->with('success', 'Coluna atualizada com sucesso.');
    }

    /**
     * Reorder columns.
     */
    public function reorder(ReorderColumnsRequest $request, KanbanBoard $board): JsonResponse
    {
        $orderedIds = $request->validated('ordered_column_ids');

        foreach ($orderedIds as $index => $id) {
            KanbanColumn::where('id', $id)
                ->where('kanban_board_id', $board->id)
                ->update(['order' => $index]);
        }

        broadcast(new ColumnsReorderedEvent($board->id, $orderedIds))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Delete a column.
     */
    public function destroy(Request $request, KanbanBoard $board, KanbanColumn $column): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $columnId = $column->id;
        $column->delete();

        broadcast(new ColumnDeletedEvent($board->id, $columnId))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Coluna excluída com sucesso.');
    }
}
