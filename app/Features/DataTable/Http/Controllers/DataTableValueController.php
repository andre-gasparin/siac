<?php

namespace App\Features\DataTable\Http\Controllers;

use App\Features\DataTable\Http\Requests\UpdateDataTableValueRequest;
use App\Http\Controllers\Controller;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DataTableValueController extends Controller
{
    public function update(UpdateDataTableValueRequest $request, Team $current_team): JsonResponse
    {
        $validated = $request->validated();
        $parameterId = (int) $validated['parameter_id'];

        $parameter = Parameter::query()
            ->where('team_id', $current_team->id)
            ->where('id', $parameterId)
            ->firstOrFail();

        $measuredAt = Carbon::parse($validated['timestamp']);
        $rawValue = $validated['value'] ?? null;
        $numericValue = $rawValue !== null && $rawValue !== '' ? (float) $rawValue : null;

        if ($numericValue === null) {
            ParameterValue::query()
                ->where('team_id', $current_team->id)
                ->where('parameter_id', $parameter->id)
                ->where('measured_at', $measuredAt)
                ->delete();
        } else {
            ParameterValue::query()->updateOrCreate(
                [
                    'team_id' => $current_team->id,
                    'parameter_id' => $parameter->id,
                    'measured_at' => $measuredAt,
                ],
                [
                    'monitored_system_id' => $parameter->monitored_system_id,
                    'measured_date' => $measuredAt->toDateString(),
                    'value' => $numericValue,
                    'source_type' => 'manual',
                ],
            );
        }

        return response()->json([
            'success' => true,
            'parameter_id' => $parameter->id,
            'timestamp' => $measuredAt->toDateTimeString(),
            'value' => $numericValue,
            'message' => 'Valor atualizado com sucesso.',
        ]);
    }
}
