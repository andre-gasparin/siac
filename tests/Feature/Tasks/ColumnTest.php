<?php

use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
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
});

test('non-admin cannot create or manipulate columns', function () {
    $this->actingAs($this->regularUser)
        ->post(route('tasks.columns.store', $this->board), [
            'name' => 'Nova Coluna',
        ])
        ->assertForbidden();
});

test('admin can create column in board', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('tasks.columns.store', $this->board), [
            'name' => 'Impedimentos',
            'color' => '#ef4444',
        ]);

    $response->assertSessionHas('success');

    $col = KanbanColumn::where('name', 'Impedimentos')->first();
    expect($col)->not->toBeNull();
    expect($col->kanban_board_id)->toBe($this->board->id);
    expect($col->color)->toBe('#ef4444');
});

test('admin can update column name and color', function () {
    $col = $this->board->columns()->create([
        'name' => 'Coluna Antiga',
        'color' => '#64748b',
        'order' => 0,
    ]);

    $this->actingAs($this->admin)
        ->put(route('tasks.columns.update', [$this->board, $col]), [
            'name' => 'Coluna Nova',
            'color' => '#10b981',
        ])
        ->assertSessionHas('success');

    expect($col->fresh()->name)->toBe('Coluna Nova');
    expect($col->fresh()->color)->toBe('#10b981');
});

test('admin can reorder columns', function () {
    $col1 = $this->board->columns()->create(['name' => 'Col 1', 'order' => 0]);
    $col2 = $this->board->columns()->create(['name' => 'Col 2', 'order' => 1]);
    $col3 = $this->board->columns()->create(['name' => 'Col 3', 'order' => 2]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('tasks.columns.reorder', $this->board), [
            'ordered_column_ids' => [$col3->id, $col1->id, $col2->id],
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($col3->fresh()->order)->toBe(0);
    expect($col1->fresh()->order)->toBe(1);
    expect($col2->fresh()->order)->toBe(2);
});

test('admin can delete column', function () {
    $col = $this->board->columns()->create(['name' => 'Para Deletar', 'order' => 0]);

    $this->actingAs($this->admin)
        ->delete(route('tasks.columns.destroy', [$this->board, $col]))
        ->assertSessionHas('success');

    expect(KanbanColumn::find($col->id))->toBeNull();
});
