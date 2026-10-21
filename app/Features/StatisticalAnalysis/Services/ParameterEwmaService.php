<?php

namespace App\Features\StatisticalAnalysis\Services;

use App\Models\Parameter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ParameterEwmaService
{
    /**
     * @param  Collection<int, Parameter>  $parameters
     * @return array<int, list<float>>
     */
    public function history(Collection $parameters, Carbon $before, int $lookback = 20): array
    {
        $parameters->load(['values' => function ($query) use ($before, $lookback): void {
            $query->where('measured_at', '<', $before)
                ->whereNotNull('value')
                ->orderByDesc('measured_at')
                ->orderByDesc('id')
                ->limit($lookback)
                ->select(['id', 'parameter_id', 'measured_at', 'value']);
        }]);

        return $parameters->mapWithKeys(fn (Parameter $parameter): array => [
            $parameter->id => array_values($parameter->values->reverse()->pluck('value')->map(fn ($value): float => (float) $value)->all()),
        ])->all();
    }
}
