<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use App\Models\MonitoredSystem;
use App\Models\Report;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserReportController extends Controller
{
    public function index(
        Request $request,
        Team $current_team,
        ConsolidatedReportService $reports,
    ): Response {
        $search = trim($request->string('search')->toString());
        $filters = ['search' => $search];

        return Inertia::render('Reports/UserIndex', [
            'reports' => Inertia::scroll(fn () => $reports->paginateUserReports($current_team, $filters)),
            'filters' => $filters,
            'currentTeam' => $current_team,
        ]);
    }

    public function show(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
    ): Response {
        abort_unless($report->team_id === $current_team->id, 404);
        abort_unless($report->status === 'completed', 404);

        return Inertia::render('Reports/UserShow', [
            ...$reports->userView($current_team, $report, $request->user()),
            'currentTeam' => $current_team,
        ]);
    }

    public function comment(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
    ): RedirectResponse {
        abort_unless($report->team_id === $current_team->id, 404);
        abort_unless($report->status === 'completed', 404);

        $validated = $request->validate([
            'report_item_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $reports->addComment(
            $current_team,
            $report,
            $request->user(),
            (int) $validated['report_item_id'],
            $validated['body'],
        );

        return back();
    }

    public function chart(
        Request $request,
        Team $current_team,
        Report $report,
        MonitoredSystem $system,
        ConsolidatedReportService $reports,
    ): JsonResponse {
        abort_unless($report->team_id === $current_team->id && $system->team_id === $current_team->id, 404);
        abort_unless($report->status === 'completed', 404);

        $validated = $request->validate([
            'parameter_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:line,bar'],
        ]);

        return response()->json($reports->chart(
            $current_team,
            $report,
            $system,
            (int) $validated['parameter_id'],
            $validated['type'],
        ));
    }
}
