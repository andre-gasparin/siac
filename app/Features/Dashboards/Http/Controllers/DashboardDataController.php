<?php

namespace App\Features\Dashboards\Http\Controllers;

use App\Features\Dashboards\Http\Requests\DashboardComponentDataRequest;
use App\Features\Dashboards\Services\DashboardComponentDataService;
use App\Http\Controllers\Controller;
use App\Models\DashboardComponent;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardDataController extends Controller
{
    public function show(
        DashboardComponentDataRequest $request,
        Team $current_team,
        DashboardComponent $component,
        DashboardComponentDataService $componentDataService
    ): JsonResponse {
        $dashboard = $component->dashboard;
        $user = $request->user();

        if ($dashboard->team_id !== $current_team->id || (! $dashboard->is_public && $dashboard->user_id !== $user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();
        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : Carbon::now()->subDays(7)->startOfDay();
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        $data = $componentDataService->getData($component, $current_team->id, $startDate, $endDate);

        return $data === null
            ? response()->json(['message' => 'Invalid component type'], 422)
            : response()->json($data);
    }
}
