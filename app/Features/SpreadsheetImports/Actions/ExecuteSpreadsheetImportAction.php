<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Models\ParameterValue;
use App\Models\SpreadsheetImportBatch;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExecuteSpreadsheetImportAction
{
    /**
     * @param array<int, array{
     *     system_id: int,
     *     parameter_id: int,
     *     final_value: float|null,
     *     measured_at: string,
     *     measured_date: string,
     *     status?: string,
     * }> $items
     * @return array{
     *     batch_id: int,
     *     saved_count: int,
     *     created_count: int,
     *     updated_count: int,
     * }
     */
    public function execute(
        Team $team,
        User $user,
        SpreadsheetTemplate $template,
        string $fileName,
        ?string $filePath,
        ?string $referenceDate,
        array $items,
    ): array {
        return DB::transaction(function () use ($team, $user, $template, $fileName, $filePath, $referenceDate, $items) {
            $batch = SpreadsheetImportBatch::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'spreadsheet_template_id' => $template->id,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'reference_date' => $referenceDate,
                'status' => 'completed',
                'saved_values_count' => 0,
                'snapshot' => [],
            ]);

            $snapshot = [];
            $createdCount = 0;
            $updatedCount = 0;

            foreach ($items as $item) {
                if (($item['status'] ?? 'valid') === 'empty_or_invalid' || ! array_key_exists('final_value', $item)) {
                    continue;
                }

                $finalValue = $item['final_value'] !== null ? (float) $item['final_value'] : null;

                $existing = ParameterValue::query()
                    ->where('team_id', $team->id)
                    ->where('parameter_id', (int) $item['parameter_id'])
                    ->where('measured_at', (string) $item['measured_at'])
                    ->first();

                if ($existing) {
                    $snapshot[] = [
                        'action' => 'update',
                        'id' => $existing->id,
                        'previous_value' => $existing->value,
                        'new_value' => $finalValue,
                        'parameter_id' => $existing->parameter_id,
                        'measured_at' => $existing->measured_at->format('Y-m-d H:i:s'),
                    ];

                    $existing->update([
                        'value' => $finalValue,
                        'updated_by' => $user->id,
                    ]);

                    $updatedCount++;
                } else {
                    $created = ParameterValue::create([
                        'team_id' => $team->id,
                        'monitored_system_id' => (int) $item['system_id'],
                        'parameter_id' => (int) $item['parameter_id'],
                        'measured_at' => (string) $item['measured_at'],
                        'measured_date' => (string) $item['measured_date'],
                        'value' => $finalValue,
                        'source_type' => 'spreadsheet',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);

                    $snapshot[] = [
                        'action' => 'create',
                        'id' => $created->id,
                        'parameter_id' => $created->parameter_id,
                        'measured_at' => $created->measured_at->format('Y-m-d H:i:s'),
                    ];

                    $createdCount++;
                }
            }

            $totalSaved = $createdCount + $updatedCount;

            $batch->update([
                'saved_values_count' => $totalSaved,
                'snapshot' => [
                    'changes' => $snapshot,
                    'items' => $items,
                    'summary' => [
                        'total_extracted' => count($items),
                        'total_new' => $createdCount,
                        'total_updates' => $updatedCount,
                        'total_empty_or_invalid' => count($items) - $totalSaved,
                    ],
                ],
            ]);

            return [
                'batch_id' => $batch->id,
                'saved_count' => $totalSaved,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
            ];
        });
    }
}
