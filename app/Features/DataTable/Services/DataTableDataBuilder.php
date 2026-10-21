<?php

namespace App\Features\DataTable\Services;

use App\Features\StatisticalAnalysis\Services\EwmaCalculator;
use App\Features\StatisticalAnalysis\Services\ParameterEwmaService;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class DataTableDataBuilder
{
    public function __construct(private ParameterEwmaService $historyService, private EwmaCalculator $calculator) {}

    /**
     * @param  array{system_ids?: list<int|string>|null, start_date?: string|null, end_date?: string|null, ewma_outside_limits?: bool|int|string, ewma_all?: bool|int|string, ewma_lookback?: int}  $filters
     * @return array<string, mixed>
     */
    public function build(Team $team, array $filters): array
    {
        $systemIds = array_values(array_filter(array_map(
            'intval',
            $filters['system_ids'] ?? [],
        )));

        if ($systemIds === []) {
            return $this->emptyResult();
        }

        $startDate = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->subDays(7)->startOfDay();

        $endDate = isset($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        $isMultiSystem = count($systemIds) > 1;
        $parameters = $this->parameters($team, $systemIds, $isMultiSystem);
        $parameterIds = $parameters->pluck('id')->all();

        if ($parameterIds === []) {
            return $this->emptyResult($isMultiSystem);
        }

        $values = ParameterValue::query()
            ->where('team_id', $team->id)
            ->whereIn('parameter_id', $parameterIds)
            ->whereBetween('measured_at', [$startDate, $endDate])
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get();

        $outsideLimits = (bool) ($filters['ewma_outside_limits'] ?? true);
        $allValues = (bool) ($filters['ewma_all'] ?? false);
        $lookback = (int) ($filters['ewma_lookback'] ?? 20);
        $history = $outsideLimits || $allValues ? $this->historyService->history($parameters, $startDate, $lookback) : [];
        [$rows, $averages] = $this->rowsAndAverages($parameters, $values, $history, $outsideLimits, $allValues, $lookback);

        return [
            'parameters' => $parameters->map(fn (Parameter $parameter): array => [
                'id' => $parameter->id,
                'name' => $parameter->name,
                'code' => $parameter->code,
                'tag' => $parameter->tag ?? $parameter->code,
                'unit' => $parameter->unit,
                'decimals' => $parameter->decimals,
                'sort_order' => $parameter->sort_order,
                'alert_1_min' => $parameter->alert_1_min,
                'alert_1_max' => $parameter->alert_1_max,
                'ewma_lambda' => $parameter->ewma_lambda,
                'ewma_level' => $parameter->ewma_level,
                'monitored_system_id' => $parameter->monitored_system_id,
                'system_name' => $parameter->monitoredSystem->name,
            ])->all(),
            'rows' => $rows,
            'averages' => $averages,
            'is_multi_system' => $isMultiSystem,
        ];
    }

    /**
     * @param  list<int>  $systemIds
     * @return Collection<int, Parameter>
     */
    private function parameters(Team $team, array $systemIds, bool $isMultiSystem): Collection
    {
        $parameters = Parameter::query()
            ->where('team_id', $team->id)
            ->whereIn('monitored_system_id', $systemIds)
            ->where('is_active', true)
            ->with(['monitoredSystem:id,name'])
            ->get();

        $ordered = $parameters->sort(fn (Parameter $left, Parameter $right): int => $left->sort_order <=> $right->sort_order ?: strnatcasecmp($left->name, $right->name))->values();

        if (! $isMultiSystem) {
            return $ordered;
        }

        $groups = [];
        foreach ($systemIds as $systemId) {
            foreach ($ordered->where('monitored_system_id', $systemId) as $parameter) {
                $key = mb_strtolower(trim($parameter->name));
                $groups[$key][] = $parameter;
            }
        }

        return new Collection(array_merge(...array_values($groups)));
    }

    /**
     * @param  Collection<int, Parameter>  $parameters
     * @param  Collection<int, ParameterValue>  $values
     * @param  array<int, list<float>>  $history
     * @return array{0: list<array<string, mixed>>, 1: array<int, array{numeric: float|null, formatted: string}>}
     */
    private function rowsAndAverages(Collection $parameters, Collection $values, array $history, bool $outsideLimits, bool $allValues, int $lookback): array
    {
        $parameterIds = $parameters->pluck('id')->all();
        $byId = $parameters->keyBy('id');
        $columnSums = array_fill_keys($parameterIds, 0.0);
        $columnCounts = array_fill_keys($parameterIds, 0);
        $grouped = [];

        foreach ($values as $value) {
            $timeKey = $value->measured_at->format('Y-m-d H:i');
            $grouped[$timeKey] ??= [
                'timestamp' => $timeKey,
                'date' => $value->measured_at->format('d/m/Y'),
                'time' => $value->measured_at->format('H:i'),
                'values' => [],
                'ewma' => [],
            ];

            if ($value->value === null) {
                continue;
            }

            $grouped[$timeKey]['values'][$value->parameter_id] = (float) $value->value;
            $parameter = $byId[$value->parameter_id];
            $numeric = (float) $value->value;
            $isOutside = ($parameter->alert_1_min !== null && $numeric < $parameter->alert_1_min)
                || ($parameter->alert_1_max !== null && $numeric > $parameter->alert_1_max);

            if ($allValues || ($outsideLimits && $isOutside)) {
                $grouped[$timeKey]['ewma'][$parameter->id] = $this->calculator->evaluate(
                    $numeric, $history[$parameter->id] ?? [], $parameter->ewma_lambda, $parameter->ewma_level, $lookback,
                );
            }

            if ($outsideLimits || $allValues) {
                $history[$parameter->id][] = $numeric;
                if (count($history[$parameter->id]) > $lookback) {
                    array_shift($history[$parameter->id]);
                }
            }
            $columnSums[$value->parameter_id] += (float) $value->value;
            $columnCounts[$value->parameter_id]++;
        }

        $averages = [];

        foreach ($parameters as $parameter) {
            $count = $columnCounts[$parameter->id];

            if ($count === 0) {
                $averages[$parameter->id] = ['numeric' => null, 'formatted' => 'SR'];

                continue;
            }

            $average = $columnSums[$parameter->id] / $count;
            $decimals = $parameter->decimals;
            $averages[$parameter->id] = [
                'numeric' => round($average, $decimals),
                'formatted' => number_format($average, $decimals, ',', '.'),
            ];
        }

        return [array_values($grouped), $averages];
    }

    /**
     * @return array{parameters: array<never>, rows: array<never>, averages: array<never>, is_multi_system: bool}
     */
    private function emptyResult(bool $isMultiSystem = false): array
    {
        return [
            'parameters' => [],
            'rows' => [],
            'averages' => [],
            'is_multi_system' => $isMultiSystem,
        ];
    }
}
