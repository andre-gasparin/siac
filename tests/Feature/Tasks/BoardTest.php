<?php

use App\Models\KanbanBoard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest cannot access kanban boards', function () {
    $this->get(route('tasks.index'))
        ->assertRedirect(route('login'));
});

test('non-admin user receives 403 forbidden', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertForbidden();
});

test('admin can access kanban boards and default board is generated', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->get(route('tasks.index'));

    $response->assertRedirect(); // redirects to the default board

    $board = KanbanBoard::first();
    expect($board)->not->toBeNull();
    expect($board->name)->toBe('Quadro Principal');
    expect($board->columns)->toHaveCount(3);
});

test('admin can create a new board with default columns', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->post(route('tasks.boards.store'), [
            'name' => 'Projetos Especiais',
            'description' => 'Tarefas de projetos estratégicos',
        ]);

    $board = KanbanBoard::where('name', 'Projetos Especiais')->first();
    expect($board)->not->toBeNull();
    expect($board->columns)->toHaveCount(3);

    $response->assertRedirect(route('tasks.boards.show', $board));
});

test('admin can update board details', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $board = KanbanBoard::create([
        'name' => 'Quadro Antigo',
        'order' => 0,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->put(route('tasks.boards.update', $board), [
            'name' => 'Quadro Renomeado',
            'description' => 'Nova descrição',
        ]);

    $response->assertSessionHas('success');
    expect($board->fresh()->name)->toBe('Quadro Renomeado');
});

test('admin can delete a board', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $board = KanbanBoard::create([
        'name' => 'Quadro Temporário',
        'order' => 0,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('tasks.boards.destroy', $board))
        ->assertRedirect(route('tasks.index'));

    expect(KanbanBoard::find($board->id))->toBeNull();
});
