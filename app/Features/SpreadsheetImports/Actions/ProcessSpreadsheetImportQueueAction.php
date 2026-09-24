<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Models\SpreadsheetImportQueue;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessSpreadsheetImportQueueAction
{
    public function __construct(
        public ParseSpreadsheetPreviewAction $previewAction,
        public ExecuteSpreadsheetImportAction $executeAction,
    ) {}

    /**
     * Process pending items in the import queue sequentially.
     *
     * @param  int  $limit  Maximum number of items to process in this run.
     * @return array{
     *     processed_count: int,
     *     success_count: int,
     *     failed_count: int,
     *     details: array<int, array<string, mixed>>,
     * }
     */
    public function execute(int $limit = 20): array
    {
        set_time_limit(0);

        $pendingItems = SpreadsheetImportQueue::query()
            ->where('status', 'pending')
            ->with(['team', 'template', 'user'])
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $processedCount = 0;
        $successCount = 0;
        $failedCount = 0;
        $details = [];

        $storageDisk = (string) config('spreadsheet_email.storage_disk', 'local');

        foreach ($pendingItems as $queueItem) {
            set_time_limit(0);
            $processedCount++;
            $queueItem->update([
                'status' => 'processing',
                'attempts' => $queueItem->attempts + 1,
                'started_at' => now(),
            ]);

            try {
                if (! Storage::disk($storageDisk)->exists($queueItem->file_path)) {
                    throw new Exception("O arquivo da planilha ('{$queueItem->file_path}') não foi encontrado no armazenamento do servidor.");
                }

                $fullPath = Storage::disk($storageDisk)->path($queueItem->file_path);
                $referenceDate = $queueItem->reference_date?->format('Y-m-d');

                // 1. Parse spreadsheet items
                $preview = $this->previewAction->execute(
                    team: $queueItem->team,
                    template: $queueItem->template,
                    filePath: $fullPath,
                    referenceDate: $referenceDate,
                );

                if (empty($preview['items'])) {
                    throw new Exception('Nenhum dado ou medição válida foi encontrada para importar de acordo com o modelo de mapeamento.');
                }

                // 2. Fallback user if enqueued by system
                $user = $queueItem->user ?? User::query()->where('current_team_id', $queueItem->team_id)->first() ?? User::first();
                if (! $user) {
                    throw new Exception('Nenhum usuário disponível para associar ao lote de importação.');
                }

                // 3. Execute DB insertion/updates
                $result = $this->executeAction->execute(
                    team: $queueItem->team,
                    user: $user,
                    template: $queueItem->template,
                    fileName: $queueItem->file_name,
                    filePath: $queueItem->file_path,
                    referenceDate: $referenceDate,
                    items: $preview['items'],
                );

                $queueItem->update([
                    'status' => 'completed',
                    'saved_values_count' => $result['saved_count'],
                    'spreadsheet_import_batch_id' => $result['batch_id'],
                    'error_message' => null,
                    'error_trace' => null,
                    'completed_at' => now(),
                ]);

                $successCount++;
                $details[] = [
                    'queue_id' => $queueItem->id,
                    'file_name' => $queueItem->file_name,
                    'status' => 'completed',
                    'saved_count' => $result['saved_count'],
                ];
            } catch (Throwable $e) {
                $failedCount++;
                Log::error("[ProcessSpreadsheetImportQueue] Erro ao processar fila ID {$queueItem->id} ({$queueItem->file_name}): ".$e->getMessage());

                $queueItem->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'error_trace' => Str::limit($e->getTraceAsString(), 5000),
                    'completed_at' => now(),
                ]);

                $details[] = [
                    'queue_id' => $queueItem->id,
                    'file_name' => $queueItem->file_name,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed_count' => $processedCount,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'details' => $details,
        ];
    }
}
