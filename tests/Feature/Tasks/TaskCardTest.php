<?php

use App\Models\KanbanBoard;
use App\Models\KanbanTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->regularUser = User::factory()->create(['is_admin' => false]);

    $this->board = KanbanBoard::create([
        'name' => 'Quadro Teste',
        'order' => 0,
        'created_by' => $this->admin->id,
    ]);

    $this->colTodo = $this->board->columns()->create(['name' => 'A Fazer', 'order' => 0]);
    $this->colDone = $this->board->columns()->create(['name' => 'Concluído', 'order' => 1]);
});

test('non-admin cannot create or manipulate task cards', function () {
    $this->actingAs($this->regularUser)
        ->post(route('tasks.cards.store', [$this->board, $this->colTodo]), [
            'title' => 'Tarefa Proibida',
        ])
        ->assertForbidden();
});

test('admin can create card with initial checklist items and assigned admin', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('tasks.cards.store', [$this->board, $this->colTodo]), [
            'title' => 'Criar Relatório Trimestral',
            'description' => 'Detalhar métricas de todas as unidades',
            'due_date' => '2026-10-15',
            'assigned_to_user_id' => $this->admin->id,
            'checklists' => [
                ['title' => 'Exportar dados'],
                ['title' => 'Revisar divergências'],
            ],
        ]);

    $response->assertCreated();
    $data = $response->json('task');

    expect($data['title'])->toBe('Criar Relatório Trimestral');
    expect($data['due_date'])->toBe('2026-10-15');
    expect($data['assigned_to_user_id'])->toBe($this->admin->id);
    expect($data['checklists'])->toHaveCount(2);

    $task = KanbanTask::where('title', 'Criar Relatório Trimestral')->first();
    expect($task)->not->toBeNull();
    expect($task->checklists)->toHaveCount(2);
});

test('admin can update card title, description and due date', function () {
    $task = KanbanTask::create([
        'kanban_board_id' => $this->board->id,
        'kanban_column_id' => $this->colTodo->id,
        'title' => 'Tarefa Original',
        'created_by' => $this->admin->id,
        'order' => 0,
    ]);

    $this->actingAs($this->admin)
        ->putJson(route('tasks.cards.update', [$this->board, $task]), [
            'title' => 'Tarefa Alterada',
            'description' => 'Nova descrição adicionada',
            'due_date' => '2026-12-01',
        ])
        ->assertOk();

    expect($task->fresh()->title)->toBe('Tarefa Alterada');
    expect($task->fresh()->description)->toBe('Nova descrição adicionada');
});

test('admin can toggle card completed status', function () {
    $task = KanbanTask::create([
        'kanban_board_id' => $this->board->id,
        'kanban_column_id' => $this->colTodo->id,
        'title' => 'Tarefa a Concluir',
        'is_completed' => false,
        'created_by' => $this->admin->id,
        'order' => 0,
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('tasks.cards.toggle', [$this->board, $task]))
        ->assertOk();

    expect($task->fresh()->is_completed)->toBeTrue();
    expect($task->fresh()->completed_at)->not->toBeNull();

    // Toggle back to not completed
    $this->actingAs($this->admin)
        ->postJson(route('tasks.cards.toggle', [$this->board, $task]))
        ->assertOk();

    expect($task->fresh()->is_completed)->toBeFalse();
    expect($task->fresh()->completed_at)->toBeNull();
});

test('admin can move card to another column and change order', function () {
    $task = KanbanTask::create([
        'kanban_board_id' => $this->board->id,
        'kanban_column_id' => $this->colTodo->id,
        'title' => 'Mover para Concluído',
        'order' => 0,
        'created_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('tasks.cards.move', [$this->board, $task]), [
            'to_column_id' => $this->colDone->id,
            'new_order' => 0,
            'reordered_task_ids' => [$task->id],
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $fresh = $task->fresh();
    expect($fresh->kanban_column_id)->toBe($this->colDone->id);
    expect($fresh->order)->toBe(0);
});

test('admin can delete a card', function () {
    $task = KanbanTask::create([
        'kanban_board_id' => $this->board->id,
        'kanban_column_id' => $this->colTodo->id,
        'title' => 'Para Deletar',
        'order' => 0,
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson(route('tasks.cards.destroy', [$this->board, $task]))
        ->assertOk();

    expect(KanbanTask::find($task->id))->toBeNull();
});
