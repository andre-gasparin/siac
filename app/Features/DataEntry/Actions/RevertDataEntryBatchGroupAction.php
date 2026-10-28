<?php

namespace App\Features\DataEntry\Actions;

use App\Features\Calculations\Services\ParameterCalculationService;
use App\Models\DataEntryBatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevertDataEntryBatchGroupAction
{
    public function __construct(
        private readonly RevertDataEntryBatchAction $revertBatchAction,
        private readonly ParameterCalculationService $calculator,
    ) {}

    /**
     * @return array{reverted_count: int, batch_group_uuid: string}
     */
    public function execute(Team $team, User $user, string $batchGroupUuid): array
    {
        if (str_starts_with($batchGroupUuid, 'legacy_')) {
            $batchId = (int) substr($batchGroupUuid, 7);
            $batch = DataEntryBatch::query()
                ->where('team_id', $team->id)
                ->where('id', $batchId)
                ->first();

            if (! $batch) {
                throw ValidationException::withMessages([
                    'batch' => ['Envio não encontrado para esta unidade.'],
                ]);
            }

            $this->revertBatchAction->execute($team, $user, $batch);

            return [
                'reverted_count' => 1,
                'batch_group_uuid' => $batchGroupUuid,
            ];
        }

        $batches = DataEntryBatch::query()
            ->where('team_id', $team->id)
            ->where('batch_group_uuid', $batchGroupUuid)
            ->get();

        if ($batches->isEmpty()) {
            throw ValidationException::withMessages([
                'batch' => ['Envio não encontrado para esta unidade.'],
            ]);
        }

        $completedBatches = $batches->where('status', 'completed');

        if ($completedBatches->isEmpty()) {
            throw ValidationException::withMessages([
                'batch' => ['Todos os sistemas deste envio já foram cancelados/excluídos.'],
            ]);
        }

        return DB::transaction(function () use ($team, $user, $completedBatches, $batchGroupUuid): array {
            $this->calculator->lock($team);
            $revertedCount = 0;
            $parameterIds = [];
            $timestamps = [];

            foreach ($completedBatches as $batch) {
                $this->revertBatchAction->execute($team, $user, $batch, false);
                array_push($parameterIds, ...($batch->parameter_ids ?? []));
                $timestamps[] = $batch->collected_at->toDateTimeString();
                $revertedCount++;
            }

            $this->calculator->execute($team, $parameterIds, $timestamps, $user);

            return [
                'reverted_count' => $revertedCount,
                'batch_group_uuid' => $batchGroupUuid,
            ];
        }, 3);
    }
}
