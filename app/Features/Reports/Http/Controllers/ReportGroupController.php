<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Http\Requests\GroupReportItemsRequest;
use App\Features\Reports\Http\Requests\UngroupReportItemsRequest;
use App\Features\Reports\Services\ReportEditorService;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class ReportGroupController extends Controller
{
    public function store(
        GroupReportItemsRequest $request,
        Team $current_team,
        ReportEditorService $editor,
    ): JsonResponse {
        $validated = $request->validated();

        return response()->json([
            'phrases' => $editor->group(
                $current_team,
                (int) $validated['report_id'],
                array_values(array_map('intval', $validated['item_ids'])),
                (int) $validated['source_item_id'],
            ),
        ]);
    }

    public function destroy(
        UngroupReportItemsRequest $request,
        Team $current_team,
        ReportEditorService $editor,
    ): JsonResponse {
        return response()->json([
            'phrases' => $editor->ungroup(
                $current_team,
                (int) $request->validated('report_item_id'),
            ),
        ]);
    }
}
