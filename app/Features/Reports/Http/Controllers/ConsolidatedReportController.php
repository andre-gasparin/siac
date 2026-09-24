<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsolidatedReportController extends Controller
{
    public function index(
        Request $request,
        Team $current_team,
        ConsolidatedReportService $reports,
    ): Response {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $filters = $reports->filters($request);

        return Inertia::render('Reports/Index', [
            'reports' => Inertia::scroll(fn () => $reports->paginate($current_team, $filters)),
            'filters' => $filters,
            'statusCounts' => $reports->statusCounts($current_team),
            'currentTeam' => $current_team,
        ]);
    }

    public function store(
        Request $request,
        Team $current_team,
        ConsolidatedReportService $reports,
    ): RedirectResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $validated = $request->validate(['date_reference' => ['required', 'date_format:Y-m-d']]);
        $report = $reports->createOrOpen($current_team, $request->user(), $validated['date_reference']);

        return to_route('reports.show', ['current_team' => $current_team, 'report' => $report]);
    }

    public function show(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
    ): Response {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $report = $reports->scopedReport($current_team, $report->id);

        return Inertia::render('Reports/Show', [
            ...$reports->view($current_team, $report),
            'currentTeam' => $current_team,
        ]);
    }

    public function preview(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
    ): Response {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $report = $reports->scopedReport($current_team, $report->id);

        return Inertia::render('Reports/Preview', [
            ...$reports->previewView($current_team, $report, $request->user()),
            'currentTeam' => $current_team,
        ]);
    }

    public function previewComment(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
    ): RedirectResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $report = $reports->scopedReport($current_team, $report->id);

        $validated = $request->validate([
            'report_item_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/'],
        ]);

        $reports->addComment(
            $current_team,
            $report,
            $request->user(),
            (int) $validated['report_item_id'],
            $validated['body'],
        );

        return back()->with('success', 'Comentário publicado.');
    }
}
