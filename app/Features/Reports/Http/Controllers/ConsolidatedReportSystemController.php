<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use App\Models\MonitoredSystem;
use App\Models\Report;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsolidatedReportSystemController extends Controller
{
    public function update(
        Request $request,
        Team $current_team,
        Report $report,
        ConsolidatedReportService $reports,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $validated = $request->validate([
            'system_id' => ['required', 'integer'],
            'show_data_results' => ['nullable', 'boolean'],
            'parent_system_id' => ['nullable', 'integer'],
            'clear_comment' => ['nullable', 'boolean'],
        ]);
        $report = $reports->scopedReport($current_team, $report->id);

        if (array_key_exists('parent_system_id', $validated)) {
            $item = $reports->setGroup(
                $current_team,
                $report,
                $request->user(),
                (int) $validated['system_id'],
                $validated['parent_system_id'] !== null ? (int) $validated['parent_system_id'] : null,
                (bool) ($validated['clear_comment'] ?? false),
            );
        } else {
            $item = $reports->setVisibility(
                $current_team,
                $report,
                $request->user(),
                (int) $validated['system_id'],
                (bool) ($validated['show_data_results'] ?? true),
            );
        }

        return response()->json([
            'item_id' => $item->id,
            'show_data_results' => $item->show_data_results,
            'parent_report_item_id' => $item->parent_report_item_id,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ]);
    }

    public function data(
        Request $request,
        Team $current_team,
        Report $report,
        MonitoredSystem $system,
        ConsolidatedReportService $reports,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $report = $reports->scopedReport($current_team, $report->id);
        $system = MonitoredSystem::query()->whereBelongsTo($current_team)->whereKey($system->id)->firstOrFail();

        $startDate = $request->string('start_date')->trim()->toString() ?: null;
        $endDate = $request->string('end_date')->trim()->toString() ?: null;

        return response()->json($reports->dataForSystem($current_team, $report, $system, $startDate, $endDate));
    }

    public function chart(
        Request $request,
        Team $current_team,
        Report $report,
        MonitoredSystem $system,
        ConsolidatedReportService $reports,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);
        $validated = $request->validate([
            'parameter_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:line,bar'],
        ]);
        $report = $reports->scopedReport($current_team, $report->id);
        $system = MonitoredSystem::query()->whereBelongsTo($current_team)->whereKey($system->id)->firstOrFail();

        return response()->json($reports->chart(
            $current_team,
            $report,
            $system,
            (int) $validated['parameter_id'],
            $validated['type'],
        ));
    }
}
