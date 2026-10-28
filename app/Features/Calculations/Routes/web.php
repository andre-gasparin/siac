<?php

use App\Features\Calculations\Http\Controllers\CalculationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}/calculos')->name('calculations.')
    ->middleware(['web', 'auth', 'verified', EnsureTeamMembership::class])
    ->group(function (): void {
        Route::get('/', [CalculationController::class, 'index'])->name('index');
        Route::post('/', [CalculationController::class, 'store'])->name('store');
        Route::put('/{calculation}', [CalculationController::class, 'update'])->name('update');
        Route::delete('/{calculation}', [CalculationController::class, 'destroy'])->name('destroy');
        Route::post('/{calculation}/recalcular', [CalculationController::class, 'recalculate'])->name('recalculate');
    });
