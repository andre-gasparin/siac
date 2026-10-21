<?php

namespace App\Features\DataEntry\Http\Controllers;

use App\Features\DataEntry\Http\Requests\PreviewDataEntryEwmaRequest;
use App\Features\StatisticalAnalysis\Services\EwmaCalculator;
use App\Features\StatisticalAnalysis\Services\ParameterEwmaService;
use App\Http\Controllers\Controller;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DataEntryEwmaController extends Controller
{
    public function __invoke(PreviewDataEntryEwmaRequest $request, Team $current_team, ParameterEwmaService $historyService, EwmaCalculator $calculator): JsonResponse
    {
        $validated = $request->validated();
        $lookback = (int) ($validated['lookback'] ?? 20);
        /** @var list<array{parameter_id: int, value: float|int|string}> $values */
        $values = $validated['values'];
        $inputs = collect($values)->keyBy('parameter_id');
        $parameters = Parameter::query()->where('team_id', $current_team->id)->whereIn('id', $inputs->keys())->get();
        $history = $historyService->history($parameters, Carbon::parse($validated['collected_at']), $lookback);
        $results = [];

        foreach ($parameters as $parameter) {
            $results[$parameter->id] = $calculator->evaluate(
                (float) $inputs[$parameter->id]['value'], $history[$parameter->id], $parameter->ewma_lambda, $parameter->ewma_level, $lookback,
            );
        }

        return response()->json(['results' => (object) $results]);
    }
}
