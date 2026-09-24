<?php

use App\Features\Reports\Http\Controllers\ChartTemplateController;
use App\Features\Reports\Http\Controllers\ConsolidatedReportController;
use App\Features\Reports\Http\Controllers\ConsolidatedReportSystemController;
use App\Features\Reports\Http\Controllers\PublicReportChartController;
use App\Features\Reports\Http\Controllers\PublicReportCommentController;
use App\Features\Reports\Http\Controllers\PublicReportController;
use App\Features\Reports\Http\Controllers\ReportAssistantController;
use App\Features\Reports\Http\Controllers\ReportChartController;
use App\Features\Reports\Http\Controllers\ReportCommentController;
use App\Features\Reports\Http\Controllers\ReportDeliveryController;
use App\Features\Reports\Http\Controllers\ReportGroupController;
use App\Features\Reports\Http\Controllers\ReportHistoryController;
use App\Features\Reports\Http\Controllers\ReportItemController;
use App\Features\Reports\Http\Controllers\ReportSuggestionController;
use App\Features\Reports\Http\Controllers\UserReportController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::get('relatorios/acesso/{token}', PublicReportController::class)
    ->middleware('throttle:reports-public-view')
    ->name('reports.public.show');
Route::post('relatorios/acesso/{token}/comentarios', PublicReportCommentController::class)
    ->middleware('throttle:reports-public-comments')
    ->name('reports.public.comments.store');
Route::get('relatorios/acesso/{token}/sistemas/{system}/grafico', PublicReportChartController::class)
    ->middleware('throttle:reports-public-view')
    ->name('reports.public.charts.show');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function (): void {
        Route::get('meus-relatorios', [UserReportController::class, 'index'])->name('reports.user.index');
        Route::get('meus-relatorios/{report}', [UserReportController::class, 'show'])->name('reports.user.show');
        Route::post('meus-relatorios/{report}/comentarios', [UserReportController::class, 'comment'])->name('reports.user.comments.store');
        Route::get('meus-relatorios/{report}/sistemas/{system}/grafico', [UserReportController::class, 'chart'])->name('reports.user.systems.chart');

        Route::get('relatorios', [ConsolidatedReportController::class, 'index'])->name('reports.index');
        Route::post('relatorios', [ConsolidatedReportController::class, 'store'])->name('reports.store');
        Route::get('relatorios/{report}', [ConsolidatedReportController::class, 'show'])->name('reports.show');
        Route::get('relatorios/{report}/preview', [ConsolidatedReportController::class, 'preview'])->name('reports.preview');
        Route::post('relatorios/{report}/preview/comentarios', [ConsolidatedReportController::class, 'previewComment'])->name('reports.preview.comments.store');
        Route::put('relatorios/{report}/sistemas', [ConsolidatedReportSystemController::class, 'update'])->name('reports.systems.update');
        Route::get('relatorios/{report}/sistemas/{system}/dados', [ConsolidatedReportSystemController::class, 'data'])->name('reports.systems.data');
        Route::get('relatorios/{report}/sistemas/{system}/grafico', [ConsolidatedReportSystemController::class, 'chart'])->name('reports.systems.chart');
        Route::post('relatorios/{report}/finalizar', [ReportDeliveryController::class, 'finalize'])->name('reports.finalize');
        Route::post('relatorios/{report}/reenviar', [ReportDeliveryController::class, 'resend'])->name('reports.resend');
        Route::delete('relatorios/{report}/destinatarios/{recipient}', [ReportDeliveryController::class, 'revoke'])->name('reports.recipients.destroy');
        Route::post('relatorios/{report}/comentarios', [ReportCommentController::class, 'store'])->name('reports.comments.store');
        Route::post('relatorios/{report}/sugestoes', [ReportSuggestionController::class, 'store'])
            ->middleware('throttle:reports-ai')
            ->name('reports.suggestions.store');
        Route::patch('relatorios/{report}/sugestoes/{suggestion}', [ReportSuggestionController::class, 'update'])->name('reports.suggestions.update');
        Route::get('reports/editor', [ReportItemController::class, 'show'])->name('reports.editor.show');
        Route::put('reports/editor', [ReportItemController::class, 'update'])->name('reports.editor.update');
        Route::get('reports/history', ReportHistoryController::class)->name('reports.history.index');
        Route::post('reports/groups', [ReportGroupController::class, 'store'])->name('reports.groups.store');
        Route::delete('reports/groups', [ReportGroupController::class, 'destroy'])->name('reports.groups.destroy');
        Route::post('reports/assistant', ReportAssistantController::class)
            ->middleware('throttle:reports-ai')
            ->name('reports.assistant.store');
        Route::post('reports/charts/data', ReportChartController::class)->name('reports.charts.data');
        Route::get('reports/chart-templates', [ChartTemplateController::class, 'index'])->name('reports.chart-templates.index');
        Route::post('reports/chart-templates', [ChartTemplateController::class, 'store'])->name('reports.chart-templates.store');
        Route::put('reports/chart-templates/{chart_template}', [ChartTemplateController::class, 'update'])->name('reports.chart-templates.update');
        Route::delete('reports/chart-templates/{chart_template}', [ChartTemplateController::class, 'destroy'])->name('reports.chart-templates.destroy');
    });
