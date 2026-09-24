<?php

namespace App\Features\DataEntry\Actions;

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
    /**
     * @param  list<array{parameter_id: int, value: float|int|string|null}>  $values
     * @return array{saved_count: int, has_comment: bool, measured_at: string, batch_id: int}
     */
    public function execute(
        Team $team,
        User $user,
        int $monitoredSystemId,
        string $collectedAt,
        array $values,
        ?string $comment = null,
    ): array {
        return DB::transaction(function () use ($team, $user, $monitoredSystemId, $collectedAt, $values, $comment): array {
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
            foreach ($values as $item) {
                $rawVal = $item['value'] ?? null;
                if ($rawVal !== null && $rawVal !== '') {
                    $validValues[(int) $item['parameter_id']] = (float) $rawVal;
                }
            }

            $trimmedComment = $comment !== null ? trim($comment) : '';

            if (empty($validValues) && $trimmedComment === '') {
                throw ValidationException::withMessages([
                    'values' => ['Preencha o valor de ao menos um parâmetro ou insira um comentário.'],
                ]);
            }

            $paramIds = [];
            if (! empty($validValues)) {
                $paramIds = array_keys($validValues);
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
            }

            // Remove any parameter values for this system and measured_at that were omitted/cleared
            $systemParamIds = Parameter::query()
                ->where('team_id', $team->id)
                ->where('monitored_system_id', $system->id)
                ->pluck('id')
                ->all();

            $clearedParamIds = array_diff($systemParamIds, $paramIds);
            if (! empty($clearedParamIds)) {
                ParameterValue::query()
                    ->where('team_id', $team->id)
                    ->where('monitored_system_id', $system->id)
                    ->where('measured_at', $measuredAt)
                    ->whereIn('parameter_id', $clearedParamIds)
                    ->delete();
            }

            $hasComment = false;
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
            } else {
                ParameterValuesComment::query()
                    ->where('team_id', $team->id)
                    ->where('monitored_system_id', $system->id)
                    ->where('measured_at', $measuredAt)
                    ->delete();
            }

            // Update or Create DataEntryBatch
            $batch = DataEntryBatch::query()
                ->where('team_id', $team->id)
                ->where('monitored_system_id', $system->id)
                ->where('collected_at', $measuredAt)
                ->where('status', 'completed')
                ->first();

            if ($batch) {
                $batch->update([
                    'user_id' => $user->id,
                    'saved_values_count' => count($validValues),
                    'parameter_ids' => $paramIds,
                    'comment' => $trimmedComment !== '' ? $trimmedComment : null,
                ]);
            } else {
                $batch = DataEntryBatch::query()->create([
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'monitored_system_id' => $system->id,
                    'collected_at' => $measuredAt,
                    'collected_date' => $measuredDate,
                    'status' => 'completed',
                    'saved_values_count' => count($validValues),
                    'parameter_ids' => $paramIds,
                    'comment' => $trimmedComment !== '' ? $trimmedComment : null,
                ]);
            }

            return [
                'saved_count' => count($validValues),
                'has_comment' => $hasComment,
                'measured_at' => $measuredAt->toDateTimeString(),
                'batch_id' => $batch->id,
            ];
        });
    }
}
