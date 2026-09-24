<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Http\Requests\ReportHistoryRequest;
use App\Features\Reports\Services\ReportEditorService;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class ReportHistoryController extends Controller
{
    public function __invoke(
        ReportHistoryRequest $request,
        Team $current_team,
        ReportEditorService $editor,
    ): JsonResponse {
        $validated = $request->validated();

        return response()->json([
            'items' => $editor->history(
                $current_team,
                (int) $validated['system_id'],
                $validated['before_date'],
                $validated['date'] ?? null,
            ),
        ]);
    }
}
