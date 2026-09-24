<?php

namespace App\Features\Dashboards\Http\Controllers;

use App\Features\Dashboards\Actions\CreateDashboardFromProposal;
use App\Features\Dashboards\Http\Requests\CreateDashboardFromProposalRequest;
use App\Features\Dashboards\Http\Requests\SuggestDashboardRequest;
use App\Features\Dashboards\Services\DashboardAiService;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DashboardAiController extends Controller
{
    public function suggest(
        SuggestDashboardRequest $request,
        Team $current_team,
        DashboardAiService $dashboardAiService
    ): JsonResponse {
        return response()->json([
            'suggestions' => $dashboardAiService->generateDashboardSuggestions(
                $current_team,
                $request->validated('objective'),
            ),
        ]);
    }

    public function store(
        CreateDashboardFromProposalRequest $request,
        Team $current_team,
        CreateDashboardFromProposal $createDashboard
    ): RedirectResponse|JsonResponse {
        $dashboard = $createDashboard->handle(
            $request->validated(),
            $request->user(),
            $current_team,
        );

        $routeParameters = [
            'current_team' => $current_team->slug,
            'dashboard' => $dashboard->id,
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'redirect_url' => route('dashboards.show', $routeParameters),
            ]);
        }

        return redirect()->route('dashboards.show', $routeParameters);
    }
}
