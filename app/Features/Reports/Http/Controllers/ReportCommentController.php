<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportCommentController extends Controller
{
    public function store(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
    ): RedirectResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $validated = $request->validate([
            'report_item_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/'],
        ]);
        $report = $reports->scopedReport($current_team, $report->id);
        $reports->addComment($current_team, $report, $request->user(), (int) $validated['report_item_id'], $validated['body']);

        return back()->with('success', 'Comentário publicado.');
    }
}
