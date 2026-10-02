<?php

use App\Features\StatisticalAnalysis\Http\Controllers\StatisticalAnalysisController;
use App\Features\StatisticalAnalysis\Http\Controllers\StatisticalAnalysisDataController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}')
    ->middleware(['web', 'auth', 'verified', EnsureTeamMembership::class])
    ->group(function (): void {
        Route::get('analise-estatistica', [StatisticalAnalysisController::class, 'index'])->name('statistical-analysis.index');
        Route::get('analise-estatistica/data', StatisticalAnalysisDataController::class)->name('statistical-analysis.data');
    });
