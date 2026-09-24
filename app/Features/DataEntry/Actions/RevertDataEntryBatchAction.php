<?php

namespace App\Features\DataEntry\Actions;

use App\Models\DataEntryBatch;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\ParameterValuesComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevertDataEntryBatchAction
{
    public function execute(Team $team, User $user, DataEntryBatch $batch): DataEntryBatch
    {
        if ($batch->team_id !== $team->id) {
            throw ValidationException::withMessages([
                'batch' => ['Este lote de envio não pertence a esta unidade.'],
            ]);
        }

        if ($batch->status === 'reverted') {
            throw ValidationException::withMessages([
                'batch' => ['Este envio já foi cancelado/excluído anteriormente.'],
            ]);
        }

        $canRevert = (bool) $user->is_admin || ($batch->user_id === $user->id);

        if (! $canRevert) {
            throw ValidationException::withMessages([
                'batch' => ['Você não tem permissão para excluir envios realizados por outros usuários.'],
            ]);
        }

        return DB::transaction(function () use ($team, $user, $batch): DataEntryBatch {
            $parameterIds = $batch->parameter_ids ?? [];

            $parameters = Parameter::query()
                ->where('team_id', $team->id)
                ->where('monitored_system_id', $batch->monitored_system_id)
                ->whereIn('id', $parameterIds)
                ->get()
                ->keyBy('id');

            $existingValues = ParameterValue::query()
                ->where('team_id', $team->id)
                ->where('monitored_system_id', $batch->monitored_system_id)
                ->where('measured_at', $batch->collected_at)
                ->whereIn('parameter_id', $parameterIds)
                ->get()
                ->keyBy('parameter_id');

            $snapshot = [];
            foreach ($parameterIds as $paramId) {
                $param = $parameters->get($paramId);
                $valRecord = $existingValues->get($paramId);

                $snapshot[] = [
                    'parameter_id' => $paramId,
                    'name' => $param ? $param->name : "Parâmetro #{$paramId}",
                    'code' => $param?->code,
                    'unit' => $param?->unit,
                    'decimals' => $param ? $param->decimals : 2,
                    'value' => $valRecord ? $valRecord->value : null,
                    'alert_1_min' => $param?->alert_1_min,
                    'alert_1_max' => $param?->alert_1_max,
                ];
            }

            // Remove values from parameter_values
            if (! empty($parameterIds)) {
                ParameterValue::query()
                    ->where('team_id', $team->id)
                    ->where('monitored_system_id', $batch->monitored_system_id)
                    ->where('measured_at', $batch->collected_at)
                    ->whereIn('parameter_id', $parameterIds)
                    ->delete();
            }

            // If comment exists, remove comment
            if (! empty($batch->comment)) {
                ParameterValuesComment::query()
                    ->where('team_id', $team->id)
                    ->where('monitored_system_id', $batch->monitored_system_id)
                    ->where('measured_at', $batch->collected_at)
                    ->delete();
            }

            $batch->update([
                'status' => 'reverted',
                'reverted_at' => Carbon::now(),
                'reverted_by' => $user->id,
                'snapshot' => $snapshot,
            ]);

            return $batch->fresh(['user', 'revertedBy', 'monitoredSystem']);
        });
    }
}
