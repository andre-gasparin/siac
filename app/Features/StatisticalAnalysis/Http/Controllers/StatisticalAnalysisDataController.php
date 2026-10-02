<?php

namespace App\Features\StatisticalAnalysis\Http\Controllers;

use App\Features\StatisticalAnalysis\Http\Requests\StatisticalAnalysisDataRequest;
use App\Features\StatisticalAnalysis\Services\StatisticalAnalysisBuilder;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class StatisticalAnalysisDataController extends Controller
{
    public function __invoke(
        StatisticalAnalysisDataRequest $request,
        Team $current_team,
        StatisticalAnalysisBuilder $builder,
    ): JsonResponse {
        return response()->json(
            $builder->build($current_team, $request->filters())
        );
    }
}
