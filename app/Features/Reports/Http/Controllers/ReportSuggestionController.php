<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Agents\ConsolidatedReportAgent;
use App\Features\Reports\Services\ConsolidatedReportService;
use App\Features\Reports\Services\ReportEditorService;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportSuggestion;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportSuggestionController extends Controller
{
    public function store(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
        ConsolidatedReportAgent $agent,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:normal,proofread'],
            'instruction' => ['nullable', 'string', 'max:5000'],
            'messages' => ['nullable', 'array', 'max:50'],
            'messages.*.role' => ['required_with:messages', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required_with:messages', 'string', 'max:5000'],
        ]);
        $report = $reports->scopedReport($current_team, $report->id);
        $result = $agent->respond(
            $current_team,
            $report,
            $request->user(),
            $validated['mode'] ?? 'normal',
            $validated['instruction'] ?? null,
            $validated['messages'] ?? [],
        );

        return response()->json($result);
    }

    public function update(
        Request $request,
        Team $current_team,
        Report $report,
        ReportSuggestion $suggestion,
        ConsolidatedReportService $reports,
        ReportEditorService $editor,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $validated = $request->validate(['decision' => ['required', 'string', 'in:accept,reject']]);
        $report = $reports->scopedReport($current_team, $report->id);
        $resolved = $editor->resolveSuggestion(
            $current_team,
            $report,
            $suggestion,
            $request->user(),
            $validated['decision'],
        );

        return response()->json(['id' => $resolved->id, 'status' => $resolved->status]);
    }
}
