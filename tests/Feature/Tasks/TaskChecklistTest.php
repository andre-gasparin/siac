<?php

use App\Models\KanbanBoard;
use App\Models\KanbanTask;
use App\Models\KanbanTaskChecklist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);

    $this->board = KanbanBoard::create([
        'name' => 'Quadro Teste',
        'order' => 0,
        'created_by' => $this->admin->id,
    ]);

    $this->column = $this->board->columns()->create(['name' => 'A Fazer', 'order' => 0]);

    $this->task = KanbanTask::create([
        'kanban_board_id' => $this->board->id,
        'kanban_column_id' => $this->column->id,
        'title' => 'Tarefa com Checklist',
        'order' => 0,
        'created_by' => $this->admin->id,
    ]);
});

test('admin can add a checklist item to a task', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('tasks.checklists.store', [$this->board, $this->task]), [
            'title' => 'Conferir backups no servidor',
        ]);

    $response->assertCreated();

    $item = KanbanTaskChecklist::where('title', 'Conferir backups no servidor')->first();
    expect($item)->not->toBeNull();
    expect($item->kanban_task_id)->toBe($this->task->id);
    expect($item->is_completed)->toBeFalse();
});

test('admin can toggle a checklist item completion status', function () {
    $item = $this->task->checklists()->create([
        'title' => 'Item de teste',
        'is_completed' => false,
        'order' => 0,
    ]);

    $this->actingAs($this->admin)
        ->putJson(route('tasks.checklists.update', [$this->board, $this->task, $item]), [
            'is_completed' => true,
        ])
        ->assertOk();

    expect($item->fresh()->is_completed)->toBeTrue();
});

test('admin can delete a checklist item', function () {
    $item = $this->task->checklists()->create([
        'title' => 'Item para exclusão',
        'is_completed' => false,
        'order' => 0,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson(route('tasks.checklists.destroy', [$this->board, $this->task, $item]))
        ->assertOk();

    expect(KanbanTaskChecklist::find($item->id))->toBeNull();
});
