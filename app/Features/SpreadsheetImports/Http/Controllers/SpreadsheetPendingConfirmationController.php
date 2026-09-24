<?php

namespace App\Features\SpreadsheetImports\Http\Controllers;

use App\Features\SpreadsheetImports\Actions\EnqueueSpreadsheetImportAction;
use App\Features\SpreadsheetImports\Actions\ParseSpreadsheetPreviewAction;
use App\Features\SpreadsheetImports\Http\Requests\BulkEnqueuePendingConfirmationRequest;
use App\Features\SpreadsheetImports\Http\Requests\EnqueuePendingConfirmationRequest;
use App\Features\SpreadsheetImports\Http\Requests\UpdatePendingConfirmationRequest;
use App\Http\Controllers\Controller;
use App\Models\SpreadsheetEmailInboxItem;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpreadsheetPendingConfirmationController extends Controller
{
    public function preview(
        Request $request,
        Team $current_team,
        SpreadsheetEmailInboxItem $item,
        ParseSpreadsheetPreviewAction $action,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($item->team_id === $current_team->id || $user->is_admin || $user->belongsToTeam($item->team), 404);

        $templateId = $request->query('template_id') ? (int) $request->query('template_id') : $item->spreadsheet_template_id;
        $referenceDate = $request->query('reference_date') ? (string) $request->query('reference_date') : $item->extracted_reference_date?->format('Y-m-d');

        $template = SpreadsheetTemplate::query()
            ->where('team_id', $item->team_id)
            ->findOrFail($templateId);

        $storageDisk = (string) config('spreadsheet_email.storage_disk', 'local');
        if (! Storage::disk($storageDisk)->exists($item->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Arquivo da planilha não encontrado no armazenamento.',
            ], 404);
        }

        $physicalPath = Storage::disk($storageDisk)->path($item->file_path);

        try {
            $preview = $action->execute(
                team: $item->team,
                template: $template,
                filePath: $physicalPath,
                referenceDate: $referenceDate,
            );

            return response()->json([
                'success' => true,
                'file_name' => $item->file_name,
                'preview' => $preview,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar prévia da planilha: '.$e->getMessage(),
            ], 422);
        }
    }

    public function update(
        UpdatePendingConfirmationRequest $request,
        Team $current_team,
        SpreadsheetEmailInboxItem $item,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($item->team_id === $current_team->id || $user->is_admin || $user->belongsToTeam($item->team), 404);

        $validated = $request->validated();

        $item->update([
            'spreadsheet_template_id' => (int) $validated['spreadsheet_template_id'],
            'extracted_reference_date' => $validated['reference_date'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item atualizado com sucesso.',
            'item' => $item->load('template:id,name'),
        ]);
    }

    public function enqueue(
        EnqueuePendingConfirmationRequest $request,
        Team $current_team,
        SpreadsheetEmailInboxItem $item,
        EnqueueSpreadsheetImportAction $action,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($item->team_id === $current_team->id || $user->is_admin || $user->belongsToTeam($item->team), 404);

        $validated = $request->validated();

        try {
            $queueItem = $action->enqueueFromInboxItem(
                inboxItem: $item,
                user: $user,
                templateId: isset($validated['spreadsheet_template_id']) ? (int) $validated['spreadsheet_template_id'] : null,
                referenceDate: $validated['reference_date'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => "Arquivo '{$item->file_name}' enviado para a fila de importação com sucesso!",
                'queue_id' => $queueItem->id,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar para fila: '.$e->getMessage(),
            ], 500);
        }
    }

    public function bulkEnqueue(
        BulkEnqueuePendingConfirmationRequest $request,
        Team $current_team,
        EnqueueSpreadsheetImportAction $action,
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $availableTeamIds = $user->is_admin
            ? Team::query()->where('is_active', true)->pluck('id')->all()
            : $user->teams()->where('teams.is_active', true)->pluck('teams.id')->all();

        $items = SpreadsheetEmailInboxItem::query()
            ->whereIn('team_id', $availableTeamIds)
            ->whereIn('id', $validated['ids'])
            ->where('status', 'pending_confirmation')
            ->get();

        $enqueuedCount = 0;
        foreach ($items as $item) {
            $action->enqueueFromInboxItem(
                inboxItem: $item,
                user: $user,
            );
            $enqueuedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$enqueuedCount} arquivos enviados para a fila de importação com sucesso!",
            'enqueued_count' => $enqueuedCount,
        ]);
    }

    public function destroy(
        Request $request,
        Team $current_team,
        SpreadsheetEmailInboxItem $item,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($item->team_id === $current_team->id || $user->is_admin || $user->belongsToTeam($item->team), 404);

        $storageDisk = (string) config('spreadsheet_email.storage_disk', 'local');
        if (Storage::disk($storageDisk)->exists($item->file_path)) {
            Storage::disk($storageDisk)->delete($item->file_path);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Arquivo descartado com sucesso.',
        ]);
    }
}
