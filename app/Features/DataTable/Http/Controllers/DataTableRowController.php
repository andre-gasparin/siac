<?php

namespace App\Features\DataTable\Http\Controllers;

use App\Features\DataTable\Http\Requests\DeleteDataTableRowRequest;
use App\Features\DataTable\Http\Requests\UpdateDataTableRowTimestampRequest;
use App\Features\DataTable\Services\DataTableMutationService;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DataTableRowController extends Controller
{
    public function destroy(DeleteDataTableRowRequest $request, Team $current_team, DataTableMutationService $mutations): JsonResponse
    {
        $deletedCount = $mutations->deleteRow($current_team, $request->user(), Carbon::parse($request->validated('timestamp')), $request->validated('parameter_ids'));

        return response()->json([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => 'Linha excluída com sucesso.',
        ]);
    }

    public function updateTimestamp(UpdateDataTableRowTimestampRequest $request, Team $current_team, DataTableMutationService $mutations): JsonResponse
    {
        $validated = $request->validated();
        $oldMeasuredAt = Carbon::parse($validated['old_timestamp']);
        $newMeasuredAt = Carbon::parse($validated['new_timestamp']);

        $updatedCount = $mutations->moveRow($current_team, $request->user(), $oldMeasuredAt, $newMeasuredAt, $validated['parameter_ids']);

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
            'old_timestamp' => $oldMeasuredAt->format('Y-m-d H:i'),
            'new_timestamp' => $newMeasuredAt->format('Y-m-d H:i'),
            'date' => $newMeasuredAt->format('d/m/Y'),
            'time' => $newMeasuredAt->format('H:i'),
            'message' => 'Data/hora da linha atualizada com sucesso.',
        ]);
    }
}
