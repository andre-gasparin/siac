<?php

use App\Models\KanbanBoard;
use App\Models\KanbanTask;
use App\Models\KanbanTaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->regularUser = User::factory()->create(['is_admin' => false]);

    $this->board = KanbanBoard::create([
        'name' => 'Quadro Teste',
        'order' => 0,
        'created_by' => $this->admin->id,
    ]);

    $this->column = $this->board->columns()->create(['name' => 'A Fazer', 'order' => 0]);

    $this->task = KanbanTask::create([
        'kanban_board_id' => $this->board->id,
        'kanban_column_id' => $this->column->id,
        'title' => 'Tarefa com Anexos',
        'order' => 0,
        'created_by' => $this->admin->id,
    ]);
});

test('admin can upload an attachment up to 25MB', function () {
    $file = UploadedFile::fake()->create('relatorio_auditoria.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($this->admin)
        ->postJson(route('tasks.attachments.store', [$this->board, $this->task]), [
            'file' => $file,
        ]);

    $response->assertCreated();

    $attachment = KanbanTaskAttachment::where('original_name', 'relatorio_auditoria.pdf')->first();
    expect($attachment)->not->toBeNull();
    expect($attachment->kanban_task_id)->toBe($this->task->id);
    expect($attachment->mime_type)->toBe('application/pdf');

    Storage::disk('local')->assertExists($attachment->file_path);
});

test('admin can download attachment privately', function () {
    $file = UploadedFile::fake()->create('planilha.xlsx', 500, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $path = $file->store("kanban_attachments/{$this->task->id}", 'local');

    $attachment = $this->task->attachments()->create([
        'user_id' => $this->admin->id,
        'original_name' => 'planilha.xlsx',
        'file_path' => $path,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'size_bytes' => 500 * 1024,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('tasks.attachments.download', [$this->board, $this->task, $attachment]));

    $response->assertOk();
});

test('non-admin cannot download attachment', function () {
    $file = UploadedFile::fake()->create('secreto.pdf', 100);
    $path = $file->store("kanban_attachments/{$this->task->id}", 'local');

    $attachment = $this->task->attachments()->create([
        'user_id' => $this->admin->id,
        'original_name' => 'secreto.pdf',
        'file_path' => $path,
        'mime_type' => 'application/pdf',
        'size_bytes' => 100 * 1024,
    ]);

    $this->actingAs($this->regularUser)
        ->get(route('tasks.attachments.download', [$this->board, $this->task, $attachment]))
        ->assertForbidden();
});

test('admin can delete an attachment', function () {
    $file = UploadedFile::fake()->create('imagem.png', 200, 'image/png');
    $path = $file->store("kanban_attachments/{$this->task->id}", 'local');

    $attachment = $this->task->attachments()->create([
        'user_id' => $this->admin->id,
        'original_name' => 'imagem.png',
        'file_path' => $path,
        'mime_type' => 'image/png',
        'size_bytes' => 200 * 1024,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson(route('tasks.attachments.destroy', [$this->board, $this->task, $attachment]))
        ->assertOk();

    expect(KanbanTaskAttachment::find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});
