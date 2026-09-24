<?php

namespace App\Features\DataTable\Services;

use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DataTableDataBuilder
{
    /**
     * @param  array{system_ids?: list<int|string>|null, start_date?: string|null, end_date?: string|null}  $filters
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
            ->get();

        [$rows, $averages] = $this->rowsAndAverages($parameters, $values);

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

        return $parameters
            ->sort(function (Parameter $left, Parameter $right) use ($isMultiSystem): int {
                if ($isMultiSystem) {
                    return strnatcasecmp($left->name, $right->name)
                        ?: $left->monitored_system_id <=> $right->monitored_system_id;
                }

                return $left->sort_order <=> $right->sort_order
                    ?: strnatcasecmp($left->name, $right->name);
            })
            ->values();
    }

    /**
     * @param  Collection<int, Parameter>  $parameters
     * @param  Collection<int, ParameterValue>  $values
     * @return array{0: list<array<string, mixed>>, 1: array<int, array{numeric: float|null, formatted: string}>}
     */
    private function rowsAndAverages(Collection $parameters, Collection $values): array
    {
        $parameterIds = $parameters->pluck('id')->all();
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
            ];

            if ($value->value === null) {
                continue;
            }

            $grouped[$timeKey]['values'][$value->parameter_id] = (float) $value->value;
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
