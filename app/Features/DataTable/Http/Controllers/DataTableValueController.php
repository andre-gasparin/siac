<?php

namespace App\Features\DataTable\Http\Controllers;

use App\Features\DataTable\Http\Requests\UpdateDataTableValueRequest;
use App\Features\DataTable\Services\DataTableMutationService;
use App\Http\Controllers\Controller;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DataTableValueController extends Controller
{
    public function update(UpdateDataTableValueRequest $request, Team $current_team, DataTableMutationService $mutations): JsonResponse
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

        $mutations->updateValue($current_team, $request->user(), $parameter, $measuredAt, $numericValue);

        return response()->json([
            'success' => true,
            'parameter_id' => $parameter->id,
            'timestamp' => $measuredAt->toDateTimeString(),
            'value' => $numericValue,
            'message' => 'Valor atualizado com sucesso.',
        ]);
    }
}
