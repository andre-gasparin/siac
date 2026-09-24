<?php

use App\Features\SpreadsheetImports\Http\Controllers\SpreadsheetEmailRuleController;
use App\Features\SpreadsheetImports\Http\Controllers\SpreadsheetImportController;
use App\Features\SpreadsheetImports\Http\Controllers\SpreadsheetImportQueueController;
use App\Features\SpreadsheetImports\Http\Controllers\SpreadsheetPendingConfirmationController;
use App\Features\SpreadsheetImports\Http\Controllers\SpreadsheetTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('{current_team}/importacao-planilhas')
    ->group(function () {
        // Upload & Import operations
        Route::get('/', [SpreadsheetImportController::class, 'index'])->name('spreadsheet-imports.index');
        Route::post('/previa', [SpreadsheetImportController::class, 'preview'])->name('spreadsheet-imports.preview');
        Route::post('/enfileirar-manual', [SpreadsheetImportController::class, 'enqueueManual'])->name('spreadsheet-imports.enqueue-manual');
        Route::post('/executar', [SpreadsheetImportController::class, 'execute'])->name('spreadsheet-imports.execute');
        Route::get('/lotes/{batch}/download', [SpreadsheetImportController::class, 'download'])->name('spreadsheet-imports.batches.download');
        Route::get('/lotes/{batch}/itens', [SpreadsheetImportController::class, 'batchItems'])->name('spreadsheet-imports.batches.items');
        Route::post('/lotes/{batch}/reverter', [SpreadsheetImportController::class, 'revert'])->name('spreadsheet-imports.revert');

        // Pending Email Confirmations
        Route::get('/confirmacoes/{item}/previa', [SpreadsheetPendingConfirmationController::class, 'preview'])->name('spreadsheet-imports.confirmations.preview');
        Route::put('/confirmacoes/{item}', [SpreadsheetPendingConfirmationController::class, 'update'])->name('spreadsheet-imports.confirmations.update');
        Route::post('/confirmacoes/{item}/enfileirar', [SpreadsheetPendingConfirmationController::class, 'enqueue'])->name('spreadsheet-imports.confirmations.enqueue');
        Route::post('/confirmacoes/enfileirar-lote', [SpreadsheetPendingConfirmationController::class, 'bulkEnqueue'])->name('spreadsheet-imports.confirmations.bulk-enqueue');
        Route::delete('/confirmacoes/{item}', [SpreadsheetPendingConfirmationController::class, 'destroy'])->name('spreadsheet-imports.confirmations.destroy');

        // Custom Queue Operations
        Route::get('/fila', [SpreadsheetImportQueueController::class, 'index'])->name('spreadsheet-imports.queue.index');
        Route::post('/fila/{queue}/tentar-novamente', [SpreadsheetImportQueueController::class, 'retry'])->name('spreadsheet-imports.queue.retry');
        Route::delete('/fila/{queue}', [SpreadsheetImportQueueController::class, 'destroy'])->name('spreadsheet-imports.queue.destroy');

        // Email Rules Management
        Route::get('/regras', [SpreadsheetEmailRuleController::class, 'index'])->name('spreadsheet-imports.rules.index');
        Route::get('/regras/criar', [SpreadsheetEmailRuleController::class, 'create'])->name('spreadsheet-imports.rules.create');
        Route::post('/regras', [SpreadsheetEmailRuleController::class, 'store'])->name('spreadsheet-imports.rules.store');
        Route::get('/regras/{rule}/editar', [SpreadsheetEmailRuleController::class, 'edit'])->name('spreadsheet-imports.rules.edit');
        Route::put('/regras/{rule}', [SpreadsheetEmailRuleController::class, 'update'])->name('spreadsheet-imports.rules.update');
        Route::delete('/regras/{rule}', [SpreadsheetEmailRuleController::class, 'destroy'])->name('spreadsheet-imports.rules.destroy');

        // Template Management
        Route::get('/modelos', [SpreadsheetTemplateController::class, 'index'])->name('spreadsheet-imports.templates.index');
        Route::get('/modelos/criar', [SpreadsheetTemplateController::class, 'create'])->name('spreadsheet-imports.templates.create');
        Route::post('/modelos', [SpreadsheetTemplateController::class, 'store'])->name('spreadsheet-imports.templates.store');
        Route::post('/modelos/previa-exemplo', [SpreadsheetTemplateController::class, 'samplePreview'])->name('spreadsheet-imports.templates.sample-preview');
        Route::get('/modelos/{template}/editar', [SpreadsheetTemplateController::class, 'edit'])->name('spreadsheet-imports.templates.edit');
        Route::put('/modelos/{template}', [SpreadsheetTemplateController::class, 'update'])->name('spreadsheet-imports.templates.update');
        Route::delete('/modelos/{template}', [SpreadsheetTemplateController::class, 'destroy'])->name('spreadsheet-imports.templates.destroy');
    });
