<?php

namespace App\Features\DataEntry\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreBatchDataEntryAction
{
    public function __construct(
        private readonly StoreDataEntryAction $storeAction,
    ) {}

    /**
     * @param  list<array{
     *     monitored_system_id: int,
     *     values: list<array{parameter_id: int, value: float|int|string|null}>,
     *     comment?: string|null
     * }>  $systems
     * @return array{
     *     total_systems_saved: int,
     *     total_values_saved: int,
     *     batches: list<array{
     *         monitored_system_id: int,
     *         saved_count: int,
     *         has_comment: bool,
     *         measured_at: string,
     *         batch_id: int
     *     }>
     * }
     */
    public function execute(
        Team $team,
        User $user,
        string $collectedAt,
        array $systems,
    ): array {
        return DB::transaction(function () use ($team, $user, $collectedAt, $systems): array {
            $savedBatches = [];
            $totalValuesSaved = 0;

            foreach ($systems as $systemData) {
                $monitoredSystemId = (int) $systemData['monitored_system_id'];
                $values = (array) ($systemData['values'] ?? []);
                $comment = isset($systemData['comment']) ? (string) $systemData['comment'] : null;

                $hasValues = false;
                foreach ($values as $item) {
                    $raw = $item['value'] ?? null;
                    if ($raw !== null && $raw !== '') {
                        $hasValues = true;
                        break;
                    }
                }
                $hasComment = $comment !== null && trim($comment) !== '';

                if (! $hasValues && ! $hasComment) {
                    continue;
                }

                $batchResult = $this->storeAction->execute(
                    team: $team,
                    user: $user,
                    monitoredSystemId: $monitoredSystemId,
                    collectedAt: $collectedAt,
                    values: $values,
                    comment: $comment,
                );

                $batchResult['monitored_system_id'] = $monitoredSystemId;
                $savedBatches[] = $batchResult;
                $totalValuesSaved += $batchResult['saved_count'];
            }

            if (empty($savedBatches)) {
                throw ValidationException::withMessages([
                    'systems' => ['Nenhum sistema possui valores ou comentários preenchidos para envio.'],
                ]);
            }

            return [
                'total_systems_saved' => count($savedBatches),
                'total_values_saved' => $totalValuesSaved,
                'batches' => $savedBatches,
            ];
        });
    }
}
