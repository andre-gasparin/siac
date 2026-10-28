<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Features\Calculations\Services\ParameterCalculationService;
use App\Models\ParameterValue;
use App\Models\SpreadsheetImportBatch;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class RevertSpreadsheetImportAction
{
    public function __construct(private readonly ParameterCalculationService $calculator) {}

    /**
     * @throws Exception
     */
    public function execute(SpreadsheetImportBatch $batch, User $user): bool
    {
        if ($batch->status === 'reverted') {
            throw new Exception('Este lote de importação já foi revertido anteriormente.');
        }

        return DB::transaction(function () use ($batch, $user) {
            $team = Team::query()->findOrFail($batch->team_id);
            $this->calculator->lock($team);
            $snapshot = $batch->snapshot ?? [];
            $changes = isset($snapshot['changes']) && is_array($snapshot['changes']) ? $snapshot['changes'] : $snapshot;
            $affected = [];

            foreach ($changes as $entry) {
                $action = $entry['action'] ?? '';
                $id = $entry['id'] ?? null;

                if (! $id) {
                    continue;
                }

                if (isset($entry['parameter_id'], $entry['measured_at'])) {
                    $affected[(string) $entry['measured_at']][] = (int) $entry['parameter_id'];
                }
                $record = ParameterValue::query()->where('team_id', $team->id)->whereKey($id)->first();

                if (! $record) {
                    continue;
                }

                if ($action === 'create') {
                    $record->delete();
                } elseif ($action === 'update') {
                    $record->update([
                        'value' => $entry['previous_value'] ?? null,
                        'updated_by' => $user->id,
                        'source_type' => $entry['previous_source_type'] ?? $record->source_type,
                        'calculation_rule_id' => $entry['previous_calculation_rule_id'] ?? null,
                    ]);
                }
            }

            $batch->update([
                'status' => 'reverted',
                'reverted_at' => Carbon::now(),
                'reverted_by' => $user->id,
            ]);

            foreach ($affected as $timestamp => $parameterIds) {
                $this->calculator->execute($team, $parameterIds, [$timestamp], $user);
            }

            return true;
        }, 3);
    }
}
