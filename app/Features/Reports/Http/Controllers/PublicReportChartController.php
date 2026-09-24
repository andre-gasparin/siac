<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReportChartController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        int $system,
        ConsolidatedReportService $reports,
    ): JsonResponse {
        $validated = $request->validate([
            'parameter_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:line,bar'],
        ]);
        $recipient = $reports->recipient($token);
        $report = Report::query()->with('team')->findOrFail($recipient->report_id);
        $item = ReportItem::query()
            ->whereBelongsTo($report)
            ->where('monitored_system_id', $system)
            ->where(function ($query): void {
                $query->where('show_data_results', true)->orWhereNotNull('comment');
            })
            ->firstOrFail();
        abort_unless($reports->isPubliclyVisible($item), 404);
        $monitoredSystem = $report->team->monitoredSystems()->whereKey($item->monitored_system_id)->firstOrFail();

        return response()->json($reports->chart(
            $report->team,
            $report,
            $monitoredSystem,
            (int) $validated['parameter_id'],
            $validated['type'],
        ));
    }
}
