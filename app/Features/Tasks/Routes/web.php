<?php

use App\Features\Tasks\Http\Controllers\TaskAttachmentController;
use App\Features\Tasks\Http\Controllers\TaskBoardController;
use App\Features\Tasks\Http\Controllers\TaskCardController;
use App\Features\Tasks\Http\Controllers\TaskChecklistController;
use App\Features\Tasks\Http\Controllers\TaskColumnController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Boards
    Route::get('tarefas', [TaskBoardController::class, 'index'])->name('tasks.index');
    Route::get('tarefas/{board}', [TaskBoardController::class, 'index'])->name('tasks.boards.show');
    Route::post('tarefas/quadros', [TaskBoardController::class, 'store'])->name('tasks.boards.store');
    Route::put('tarefas/quadros/{board}', [TaskBoardController::class, 'update'])->name('tasks.boards.update');
    Route::delete('tarefas/quadros/{board}', [TaskBoardController::class, 'destroy'])->name('tasks.boards.destroy');

    // Columns
    Route::post('tarefas/{board}/colunas', [TaskColumnController::class, 'store'])->name('tasks.columns.store');
    Route::put('tarefas/{board}/colunas/{column}', [TaskColumnController::class, 'update'])->name('tasks.columns.update');
    Route::post('tarefas/{board}/colunas/reordenar', [TaskColumnController::class, 'reorder'])->name('tasks.columns.reorder');
    Route::delete('tarefas/{board}/colunas/{column}', [TaskColumnController::class, 'destroy'])->name('tasks.columns.destroy');

    // Cards (Tasks)
    Route::post('tarefas/{board}/colunas/{column}/tarefas', [TaskCardController::class, 'store'])->name('tasks.cards.store');
    Route::put('tarefas/{board}/tarefas/{task}', [TaskCardController::class, 'update'])->name('tasks.cards.update');
    Route::post('tarefas/{board}/tarefas/{task}/mover', [TaskCardController::class, 'move'])->name('tasks.cards.move');
    Route::post('tarefas/{board}/tarefas/{task}/concluir', [TaskCardController::class, 'toggleCompleted'])->name('tasks.cards.toggle');
    Route::delete('tarefas/{board}/tarefas/{task}', [TaskCardController::class, 'destroy'])->name('tasks.cards.destroy');

    // Checklists
    Route::post('tarefas/{board}/tarefas/{task}/checklist', [TaskChecklistController::class, 'store'])->name('tasks.checklists.store');
    Route::put('tarefas/{board}/tarefas/{task}/checklist/{checklist}', [TaskChecklistController::class, 'update'])->name('tasks.checklists.update');
    Route::delete('tarefas/{board}/tarefas/{task}/checklist/{checklist}', [TaskChecklistController::class, 'destroy'])->name('tasks.checklists.destroy');

    // Attachments
    Route::post('tarefas/{board}/tarefas/{task}/anexos', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
    Route::get('tarefas/{board}/tarefas/{task}/anexos/{attachment}', [TaskAttachmentController::class, 'download'])->name('tasks.attachments.download');
    Route::delete('tarefas/{board}/tarefas/{task}/anexos/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('tasks.attachments.destroy');
});
