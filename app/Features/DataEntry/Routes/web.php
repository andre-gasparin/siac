<?php

use App\Features\DataEntry\Http\Controllers\DataEntryController;
use App\Features\DataEntry\Http\Controllers\DataEntryHistoryController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function (): void {
        Route::get('entrada-dados', [DataEntryController::class, 'index'])->name('data-entry.index');
        Route::get('entrada-dados/valores', [DataEntryController::class, 'entries'])->name('data-entry.entries');
        Route::post('entrada-dados', [DataEntryController::class, 'store'])->name('data-entry.store');
        Route::post('entrada-dados/lote', [DataEntryController::class, 'storeBatch'])->name('data-entry.store-batch');

        Route::get('entrada-dados/historico', [DataEntryHistoryController::class, 'index'])->name('data-entry.history');
        Route::get('entrada-dados/historico/envio/{batch_group_uuid}', [DataEntryHistoryController::class, 'showGroup'])->name('data-entry.history.show-group');
        Route::delete('entrada-dados/historico/envio/{batch_group_uuid}', [DataEntryHistoryController::class, 'destroyGroup'])->name('data-entry.history.destroy-group');
        Route::delete('entrada-dados/historico/{batch}', [DataEntryHistoryController::class, 'destroy'])->name('data-entry.history.destroy');
    });
