<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Models\ParameterValue;
use App\Models\SpreadsheetImportBatch;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class RevertSpreadsheetImportAction
{
    /**
     * @throws Exception
     */
    public function execute(SpreadsheetImportBatch $batch, User $user): bool
    {
        if ($batch->status === 'reverted') {
            throw new Exception('Este lote de importação já foi revertido anteriormente.');
        }

        return DB::transaction(function () use ($batch, $user) {
            $snapshot = $batch->snapshot ?? [];
            $changes = isset($snapshot['changes']) && is_array($snapshot['changes']) ? $snapshot['changes'] : $snapshot;

            foreach ($changes as $entry) {
                $action = $entry['action'] ?? '';
                $id = $entry['id'] ?? null;

                if (! $id) {
                    continue;
                }

                $record = ParameterValue::query()->find($id);

                if (! $record) {
                    continue;
                }

                if ($action === 'create') {
                    $record->delete();
                } elseif ($action === 'update') {
                    $record->update([
                        'value' => $entry['previous_value'] ?? null,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            $batch->update([
                'status' => 'reverted',
                'reverted_at' => Carbon::now(),
                'reverted_by' => $user->id,
            ]);

            return true;
        });
    }
}
