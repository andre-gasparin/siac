<?php

namespace App\Features\DataEntry\Actions;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
     *     batch_group_uuid: string,
     *     total_systems_saved: int,
     *     total_values_saved: int,
     *     batches: list<array{
     *         monitored_system_id: int,
     *         saved_count: int,
     *         cleared_count: int,
     *         has_comment: bool,
     *         measured_at: string,
     *         batch_id: int,
     *         batch_group_uuid: ?string
     *     }>
     * }
     */
    public function execute(
        Team $team,
        User $user,
        string $collectedAt,
        array $systems,
        ?string $responsible = null,
    ): array {
        return DB::transaction(function () use ($team, $user, $collectedAt, $systems, $responsible): array {
            $savedBatches = [];
            $totalValuesSaved = 0;
            $batchGroupUuid = (string) Str::uuid();

            foreach ($systems as $systemData) {
                $monitoredSystemId = (int) $systemData['monitored_system_id'];
                $values = (array) ($systemData['values'] ?? []);
                $hasCommentKey = array_key_exists('comment', $systemData);
                $comment = $hasCommentKey ? ($systemData['comment'] !== null ? (string) $systemData['comment'] : '') : null;

                try {
                    $batchResult = $this->storeAction->execute(
                        team: $team,
                        user: $user,
                        monitoredSystemId: $monitoredSystemId,
                        collectedAt: $collectedAt,
                        values: $values,
                        comment: $comment,
                        responsible: $responsible,
                        batchGroupUuid: $batchGroupUuid,
                    );

                    $batchResult['monitored_system_id'] = $monitoredSystemId;
                    $savedBatches[] = $batchResult;
                    $totalValuesSaved += $batchResult['saved_count'];
                } catch (ValidationException $e) {
                    $errors = $e->errors();
                    if (isset($errors['values']) && in_array('Preencha o valor de ao menos um parâmetro ou insira um comentário.', $errors['values'], true)) {
                        continue;
                    }
                    throw $e;
                }
            }

            if (empty($savedBatches)) {
                throw ValidationException::withMessages([
                    'systems' => ['Nenhum sistema possui valores ou comentários preenchidos para envio.'],
                ]);
            }

            return [
                'batch_group_uuid' => $batchGroupUuid,
                'total_systems_saved' => count($savedBatches),
                'total_values_saved' => $totalValuesSaved,
                'batches' => $savedBatches,
            ];
        });
    }
}
