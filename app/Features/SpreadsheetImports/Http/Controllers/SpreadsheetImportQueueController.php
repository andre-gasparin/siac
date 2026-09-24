<?php

namespace App\Features\SpreadsheetImports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SpreadsheetImportQueue;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpreadsheetImportQueueController extends Controller
{
    public function index(Request $request, Team $current_team): JsonResponse
    {
        $items = SpreadsheetImportQueue::query()
            ->where('team_id', $current_team->id)
            ->with(['template:id,name', 'user:id,name', 'batch:id,saved_values_count'])
            ->latest('id')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'queue' => $items,
        ]);
    }

    public function retry(
        Request $request,
        Team $current_team,
        SpreadsheetImportQueue $queue,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($queue->team_id === $current_team->id || $user->is_admin || $user->belongsToTeam($queue->team), 404);

        $queue->update([
            'status' => 'pending',
            'error_message' => null,
            'error_trace' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Item #{$queue->id} reenviado para processamento na fila.",
            'item' => $queue->load(['template:id,name', 'user:id,name']),
        ]);
    }

    public function destroy(
        Request $request,
        Team $current_team,
        SpreadsheetImportQueue $queue,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($queue->team_id === $current_team->id || $user->is_admin || $user->belongsToTeam($queue->team), 404);

        $queue->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removido da fila com sucesso.',
        ]);
    }
}
