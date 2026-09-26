<?php

namespace App\Features\Tasks\Http\Controllers;

use App\Features\Tasks\Events\BoardUpdatedEvent;
use App\Features\Tasks\Http\Requests\StoreBoardRequest;
use App\Features\Tasks\Http\Requests\UpdateBoardRequest;
use App\Features\Tasks\Services\TaskBoardService;
use App\Http\Controllers\Controller;
use App\Models\KanbanBoard;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function __construct(
        protected TaskBoardService $service
    ) {}

    /**
     * Display the Kanban board.
     */
    public function index(Request $request, ?KanbanBoard $board = null): Response|RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        if (! $board || ! $board->exists) {
            $board = $this->service->ensureDefaultBoard($request->user());

            return redirect()->route('tasks.boards.show', $board);
        }

        $allBoards = KanbanBoard::query()
            ->orderBy('order')
            ->select('id', 'name', 'order')
            ->get();

        $administrators = User::query()
            ->where('is_admin', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tasks/Index', [
            'board' => $this->service->formatBoard($board),
            'boards' => $allBoards,
            'administrators' => $administrators,
        ]);
    }

    /**
     * Store a new board.
     */
    public function store(StoreBoardRequest $request): RedirectResponse
    {
        $maxOrder = (int) KanbanBoard::max('order');

        $board = KanbanBoard::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'order' => $maxOrder + 1,
            'created_by' => $request->user()->id,
        ]);

        $defaultColumns = [
            ['name' => 'A Fazer', 'color' => '#64748b', 'order' => 0],
            ['name' => 'Em Andamento', 'color' => '#3b82f6', 'order' => 1],
            ['name' => 'Concluído', 'color' => '#10b981', 'order' => 2],
        ];

        foreach ($defaultColumns as $col) {
            $board->columns()->create($col);
        }

        return redirect()->route('tasks.boards.show', $board)->with('success', 'Quadro criado com sucesso.');
    }

    /**
     * Update an existing board.
     */
    public function update(UpdateBoardRequest $request, KanbanBoard $board): RedirectResponse
    {
        $board->update($request->validated());

        broadcast(new BoardUpdatedEvent(
            $board->id,
            ['id' => $board->id, 'name' => $board->name, 'description' => $board->description]
        ))->toOthers();

        return back()->with('success', 'Quadro atualizado com sucesso.');
    }

    /**
     * Delete a board.
     */
    public function destroy(Request $request, KanbanBoard $board): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $board->delete();

        return redirect()->route('tasks.index')->with('success', 'Quadro excluído com sucesso.');
    }
}
