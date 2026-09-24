<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Http\Requests\ReportChartDataRequest;
use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class ReportChartController extends Controller
{
    public function __invoke(
        ReportChartDataRequest $request,
        Team $current_team,
        ConsolidatedReportService $reports,
    ): JsonResponse {
        $validated = $request->validated();

        return response()->json($reports->chartData(
            $current_team,
            $validated['start_date'],
            $validated['end_date'],
            $validated['series'],
        ));
    }
}
