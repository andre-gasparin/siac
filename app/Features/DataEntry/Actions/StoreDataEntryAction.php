<?php

namespace App\Features\DataEntry\Actions;

use App\Features\Calculations\Services\ParameterCalculationService;
use App\Models\DataEntryBatch;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\ParameterValuesComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreDataEntryAction
{
    public function __construct(private readonly ParameterCalculationService $calculator) {}

    /**
     * @param  list<array{parameter_id: int, value: float|int|string|null}>  $values
     * @return array{saved_count: int, cleared_count: int, has_comment: bool, measured_at: string, batch_id: int, batch_group_uuid: ?string}
     */
    public function execute(
        Team $team,
        User $user,
        int $monitoredSystemId,
        string $collectedAt,
        array $values,
        ?string $comment = null,
        ?string $responsible = null,
        ?string $batchGroupUuid = null,
        bool $recalculate = true,
    ): array {
        return DB::transaction(function () use ($team, $user, $monitoredSystemId, $collectedAt, $values, $comment, $responsible, $batchGroupUuid, $recalculate): array {
            $this->calculator->lock($team);
            $system = MonitoredSystem::query()
                ->where('team_id', $team->id)
                ->where('id', $monitoredSystemId)
                ->first();

            if (! $system) {
                throw ValidationException::withMessages([
                    'monitored_system_id' => ['O sistema selecionado não pertence a esta unidade.'],
                ]);
            }

            $measuredAt = Carbon::parse($collectedAt);
            $measuredDate = $measuredAt->toDateString();

            $validValues = [];
            $rawClearedIds = [];

            foreach ($values as $item) {
                $parameterId = (int) $item['parameter_id'];
                if (! $parameterId) {
                    continue;
                }

                $rawVal = $item['value'] ?? null;
                if ($rawVal !== null && $rawVal !== '') {
                    $validValues[$parameterId] = (float) $rawVal;
                } else {
                    $rawClearedIds[] = $parameterId;
                }
            }

            // Somente considerar como cleared os parâmetros que já existiam salvos no banco para este measured_at
            $clearedParamIds = [];
            if (! empty($rawClearedIds)) {
                $clearedParamIds = ParameterValue::query()
                    ->where('team_id', $team->id)
                    ->where('monitored_system_id', $system->id)
                    ->where('measured_at', $measuredAt)
                    ->whereIn('parameter_id', $rawClearedIds)
                    ->pluck('parameter_id')
                    ->all();
            }

            $trimmedComment = $comment !== null ? trim($comment) : '';
            $hasCommentInput = $comment !== null;

            $existingComment = null;
            if ($hasCommentInput) {
                $existingComment = ParameterValuesComment::query()
                    ->where('team_id', $team->id)
                    ->where('monitored_system_id', $system->id)
                    ->where('measured_at', $measuredAt)
                    ->first();
            }

            $isClearingComment = $hasCommentInput && $trimmedComment === '' && $existingComment !== null;

            if (empty($validValues) && empty($clearedParamIds) && ($trimmedComment === '' && ! $isClearingComment)) {
                throw ValidationException::withMessages([
                    'values' => ['Preencha o valor de ao menos um parâmetro ou insira um comentário.'],
                ]);
            }

            $paramIds = array_values(array_unique(array_merge(array_keys($validValues), $clearedParamIds)));
            if (! empty($paramIds)) {
                $allowedParamsCount = Parameter::query()
                    ->where('team_id', $team->id)
                    ->where('monitored_system_id', $system->id)
                    ->whereIn('id', $paramIds)
                    ->count();

                if ($allowedParamsCount !== count($paramIds)) {
                    throw ValidationException::withMessages([
                        'values' => ['Um ou mais parâmetros informados são inválidos para este sistema.'],
                    ]);
                }

                foreach ($validValues as $parameterId => $numericValue) {
                    $existing = ParameterValue::query()
                        ->where('team_id', $team->id)
                        ->where('parameter_id', $parameterId)
                        ->where('measured_at', $measuredAt)
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'monitored_system_id' => $system->id,
                            'measured_date' => $measuredDate,
                            'value' => $numericValue,
                            'source_type' => 'manual',
                            'calculation_rule_id' => null,
                            'updated_by' => $user->id,
                        ]);
                    } else {
                        ParameterValue::query()->create([
                            'team_id' => $team->id,
                            'monitored_system_id' => $system->id,
                            'parameter_id' => $parameterId,
                            'measured_at' => $measuredAt,
                            'measured_date' => $measuredDate,
                            'value' => $numericValue,
                            'source_type' => 'manual',
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }
                }

                if (! empty($clearedParamIds)) {
                    ParameterValue::query()
                        ->where('team_id', $team->id)
                        ->where('monitored_system_id', $system->id)
                        ->where('measured_at', $measuredAt)
                        ->whereIn('parameter_id', $clearedParamIds)
                        ->delete();
                }
            }

            $hasComment = false;
            if ($hasCommentInput) {
                if ($trimmedComment !== '') {
                    ParameterValuesComment::query()->updateOrCreate(
                        [
                            'team_id' => $team->id,
                            'monitored_system_id' => $system->id,
                            'measured_at' => $measuredAt,
                        ],
                        [
                            'measured_date' => $measuredDate,
                            'comment' => $trimmedComment,
                            'created_by' => $user->id,
                        ],
                    );
                    $hasComment = true;
                } elseif ($existingComment) {
                    $existingComment->delete();
                }
            }

            $batch = DataEntryBatch::query()->create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'responsible' => $responsible,
                'monitored_system_id' => $system->id,
                'batch_group_uuid' => $batchGroupUuid,
                'collected_at' => $measuredAt,
                'collected_date' => $measuredDate,
                'status' => 'completed',
                'saved_values_count' => count($validValues) + count($clearedParamIds),
                'parameter_ids' => $paramIds,
                'comment' => $trimmedComment !== '' ? $trimmedComment : null,
            ]);

            if ($recalculate) {
                $this->calculator->execute($team, $paramIds, [$measuredAt->toDateTimeString()], $user);
            }

            return [
                'saved_count' => count($validValues),
                'cleared_count' => count($clearedParamIds),
                'has_comment' => $hasComment,
                'measured_at' => $measuredAt->toDateTimeString(),
                'batch_id' => $batch->id,
                'batch_group_uuid' => $batchGroupUuid,
            ];
        }, 3);
    }
}
