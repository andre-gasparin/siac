<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Http\Requests\ReportEditorContextRequest;
use App\Features\Reports\Http\Requests\SaveReportItemRequest;
use App\Features\Reports\Services\ReportEditorService;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReportItemController extends Controller
{
    public function show(
        ReportEditorContextRequest $request,
        Team $current_team,
        ReportEditorService $editor,
    ): JsonResponse {
        $validated = $request->validated();

        return response()->json($editor->context(
            $current_team,
            $validated['date_reference'],
            (int) $validated['system_id'],
        ));
    }

    public function update(
        SaveReportItemRequest $request,
        Team $current_team,
        ReportEditorService $editor,
    ): JsonResponse {
        try {
            return response()->json($editor->save($current_team, $request->user(), $request->validated()));
        } catch (ConflictHttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }
}
