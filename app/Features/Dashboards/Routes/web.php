<?php

use App\Features\Dashboards\Http\Controllers\DashboardAiController;
use App\Features\Dashboards\Http\Controllers\DashboardComponentController;
use App\Features\Dashboards\Http\Controllers\DashboardController;
use App\Features\Dashboards\Http\Controllers\DashboardDataController;
use App\Features\Dashboards\Http\Controllers\ParameterSearchController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::post('dashboards/ai-suggest', [DashboardAiController::class, 'suggest'])->name('dashboards.ai-suggest');
        Route::post('dashboards/ai-create', [DashboardAiController::class, 'store'])->name('dashboards.ai-create');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboards', [DashboardController::class, 'index'])->name('dashboards.index');
        Route::post('dashboards', [DashboardController::class, 'store'])->name('dashboards.store');
        Route::get('dashboards/{dashboard}', [DashboardController::class, 'show'])->name('dashboards.show');
        Route::put('dashboards/{dashboard}', [DashboardController::class, 'update'])->name('dashboards.update');
        Route::delete('dashboards/{dashboard}', [DashboardController::class, 'destroy'])->name('dashboards.destroy');

        Route::patch('dashboards/{dashboard}/grid', [DashboardComponentController::class, 'updateGrid'])->name('dashboards.grid.update');
        Route::post('dashboards/{dashboard}/components', [DashboardComponentController::class, 'store'])->name('dashboards.components.store');
        Route::put('dashboards/{dashboard}/components/{component}', [DashboardComponentController::class, 'update'])->name('dashboards.components.update');
        Route::delete('dashboards/{dashboard}/components/{component}', [DashboardComponentController::class, 'destroy'])->name('dashboards.components.destroy');

        Route::get('dashboards/components/{component}/data', [DashboardDataController::class, 'show'])->name('dashboards.components.data');
        Route::get('parameters/search', ParameterSearchController::class)->name('parameters.search');
    });
