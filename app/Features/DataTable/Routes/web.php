<?php

use App\Features\DataTable\Http\Controllers\DataTableController;
use App\Features\DataTable\Http\Controllers\DataTableDataController;
use App\Features\DataTable\Http\Controllers\DataTableRowController;
use App\Features\DataTable\Http\Controllers\DataTableValueController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function (): void {
        Route::get('tabela-dados', [DataTableController::class, 'index'])->name('data-table.index');
        Route::get('tabela-dados/data', DataTableDataController::class)->name('data-table.data');
        Route::put('tabela-dados/cell', [DataTableValueController::class, 'update'])->name('data-table.cell.update');
        Route::put('tabela-dados/rows/timestamp', [DataTableRowController::class, 'updateTimestamp'])->name('data-table.rows.timestamp.update');
        Route::delete('tabela-dados/rows', [DataTableRowController::class, 'destroy'])->name('data-table.rows.destroy');
    });
