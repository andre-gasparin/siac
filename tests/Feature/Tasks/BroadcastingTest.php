<?php

use App\Features\Tasks\Events\BoardUpdatedEvent;
use App\Features\Tasks\Events\ColumnDeletedEvent;
use App\Features\Tasks\Events\ColumnSavedEvent;
use App\Features\Tasks\Events\ColumnsReorderedEvent;
use App\Features\Tasks\Events\TaskDeletedEvent;
use App\Features\Tasks\Events\TaskMovedEvent;
use App\Features\Tasks\Events\TaskSavedEvent;
use App\Models\KanbanBoard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

test('presence channel authorizes admin with user data and rejects non-admin', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Admin Teste',
        'email' => 'admin@teste.com',
    ]);

    $regularUser = User::factory()->create([
        'is_admin' => false,
        'name' => 'User Teste',
        'email' => 'user@teste.com',
    ]);

    $board = KanbanBoard::create([
        'name' => 'Quadro Broad',
        'order' => 0,
        'created_by' => $admin->id,
    ]);

    $callback = Broadcast::driver()->getChannels()->get('kanban.board.{boardId}');
    expect($callback)->not->toBeNull();

    // Call callback with admin
    $adminResult = $callback($admin, (string) $board->id);
    expect($adminResult)->toBeArray();
    expect($adminResult['id'])->toBe($admin->id);
    expect($adminResult['name'])->toBe('Admin Teste');
    expect($adminResult['email'])->toBe('admin@teste.com');

    // Call callback with regular non-admin user
    $userResult = $callback($regularUser, (string) $board->id);
    expect($userResult)->toBeFalse();
});

test('broadcasting events instantiate with correct channels and names', function () {
    $taskSaved = new TaskSavedEvent(5, ['id' => 10, 'title' => 'Test'], isNew: true);
    expect($taskSaved->broadcastOn()[0]->name)->toBe('presence-kanban.board.5');
    expect($taskSaved->broadcastAs())->toBe('TaskSaved');

    $taskMoved = new TaskMovedEvent(5, 10, 2, 3, 0, [10]);
    expect($taskMoved->broadcastOn()[0]->name)->toBe('presence-kanban.board.5');
    expect($taskMoved->broadcastAs())->toBe('TaskMoved');

    $taskDeleted = new TaskDeletedEvent(5, 10, 2);
    expect($taskDeleted->broadcastOn()[0]->name)->toBe('presence-kanban.board.5');
    expect($taskDeleted->broadcastAs())->toBe('TaskDeleted');

    $colSaved = new ColumnSavedEvent(5, ['id' => 2, 'name' => 'Nova Coluna'], isNew: true);
    expect($colSaved->broadcastOn()[0]->name)->toBe('presence-kanban.board.5');
    expect($colSaved->broadcastAs())->toBe('ColumnSaved');

    $colDeleted = new ColumnDeletedEvent(5, 2);
    expect($colDeleted->broadcastOn()[0]->name)->toBe('presence-kanban.board.5');
    expect($colDeleted->broadcastAs())->toBe('ColumnDeleted');

    $colsReordered = new ColumnsReorderedEvent(5, [3, 2, 1]);
    expect($colsReordered->broadcastOn()[0]->name)->toBe('presence-kanban.board.5');
    expect($colsReordered->broadcastAs())->toBe('ColumnsReordered');

    $boardUpdated = new BoardUpdatedEvent(5, ['name' => 'Novo Nome']);
    expect($boardUpdated->broadcastOn()[0]->name)->toBe('presence-kanban.board.5');
    expect($boardUpdated->broadcastAs())->toBe('BoardUpdated');
});
