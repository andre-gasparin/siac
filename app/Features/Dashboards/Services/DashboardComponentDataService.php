<?php

namespace App\Features\Dashboards\Services;

use App\Models\DashboardComponent;
use App\Models\Parameter;
use App\Models\ParameterValue;
use Illuminate\Support\Carbon;

class DashboardComponentDataService
{
    /**
     * @return array<string, mixed>|null
     */
    public function getData(
        DashboardComponent $component,
        int $teamId,
        Carbon $startDate,
        Carbon $endDate
    ): ?array {
        $settings = $component->settings ?? [];

        return match ($component->type) {
            'indicator' => $this->indicatorData($component, $settings, $teamId, $startDate, $endDate),
            'chart' => $this->chartData($component, $settings, $teamId, $startDate, $endDate),
            'text' => $this->textData($component, $settings),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function indicatorData(
        DashboardComponent $component,
        array $settings,
        int $teamId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $parameterId = $settings['parameter_id'] ?? null;
        $aggregation = strtolower($settings['aggregation'] ?? 'avg');
        $parameter = $parameterId
            ? Parameter::query()
                ->with('monitoredSystem:id,name')
                ->where('team_id', $teamId)
                ->whereKey($parameterId)
                ->first()
            : null;

        $query = ParameterValue::query()
            ->where('team_id', $teamId)
            ->whereBetween('measured_at', [$startDate, $endDate]);

        if ($parameterId) {
            $query->where('parameter_id', $parameterId);
        }

        $value = match ($aggregation) {
            'sum' => $query->sum('value'),
            'count' => $query->count(),
            'max' => $query->max('value'),
            'min' => $query->min('value'),
            default => $query->avg('value'),
        };

        $decimals = $parameter instanceof Parameter ? $parameter->decimals : 2;
        $parameterDisplayName = $parameter instanceof Parameter && $parameter->monitoredSystem
            ? "{$parameter->monitoredSystem->name} - {$parameter->name}"
            : null;

        return [
            'id' => $component->id,
            'type' => 'indicator',
            'title' => $settings['title'] ?? ($parameter instanceof Parameter ? $parameter->name : 'Indicador'),
            'value' => $value ? round((float) $value, $decimals) : 0,
            'formatted_value' => is_numeric($value) ? number_format((float) $value, $decimals, ',', '.') : '0',
            'unit' => $parameter instanceof Parameter ? $parameter->unit : null,
            'aggregation' => $aggregation,
            'parameter_id' => $parameterId,
            'parameter_name' => $parameter?->name,
            'system_name' => $parameter?->monitoredSystem?->name,
            'parameter_display_name' => $parameterDisplayName,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function chartData(
        DashboardComponent $component,
        array $settings,
        int $teamId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $resultSeries = [];
        $seriesSettings = $this->seriesSettings($settings);
        $parameterIds = collect($seriesSettings)
            ->pluck('parameter_id')
            ->filter()
            ->map(fn (mixed $parameterId): int => (int) $parameterId)
            ->unique()
            ->values();
        $parameters = Parameter::query()
            ->select(['id', 'team_id', 'monitored_system_id', 'name', 'unit'])
            ->with('monitoredSystem:id,name')
            ->where('team_id', $teamId)
            ->whereKey($parameterIds)
            ->get()
            ->keyBy('id');

        foreach ($seriesSettings as $series) {
            $parameterId = (int) ($series['parameter_id'] ?? 0);

            if ($parameterId < 1) {
                continue;
            }

            $parameter = $parameters->get($parameterId);
            $parameterDisplayName = $parameter instanceof Parameter && $parameter->monitoredSystem
                ? "{$parameter->monitoredSystem->name} - {$parameter->name}"
                : $parameter?->name;
            $values = ParameterValue::query()
                ->where('team_id', $teamId)
                ->where('parameter_id', $parameterId)
                ->whereBetween('measured_at', [$startDate, $endDate])
                ->oldest('measured_at')
                ->get(['measured_at', 'value']);

            $resultSeries[] = [
                'parameter_id' => $parameterId,
                'label' => $series['label'] ?? ($parameterDisplayName ?: "Parâmetro #{$parameterId}"),
                'parameter_name' => $parameter?->name,
                'system_name' => $parameter?->monitoredSystem?->name,
                'parameter_display_name' => $parameterDisplayName,
                'chart_type' => $series['chart_type'] ?? 'line',
                'color' => $series['color'] ?? '#3B82F6',
                'stroke_width' => (int) ($series['stroke_width'] ?? 2),
                'independent_axis' => (bool) ($series['independent_axis'] ?? false),
                'axis_position' => $series['axis_position'] ?? 'left',
                'min_val' => isset($series['min_val']) ? (float) $series['min_val'] : null,
                'max_val' => isset($series['max_val']) ? (float) $series['max_val'] : null,
                'unit' => $parameter instanceof Parameter ? $parameter->unit : null,
                'data' => $values->map(fn (ParameterValue $value): array => [
                    'timestamp' => $value->measured_at->toIso8601String(),
                    'formatted_time' => $value->measured_at->format('d/m H:i'),
                    'value' => $value->value,
                ]),
            ];
        }

        return [
            'id' => $component->id,
            'type' => 'chart',
            'title' => $settings['title'] ?? 'Gráfico',
            'series' => $resultSeries,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function textData(DashboardComponent $component, array $settings): array
    {
        return [
            'id' => $component->id,
            'type' => 'text',
            'title' => $settings['title'] ?? 'Texto',
            'content' => $settings['content'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function seriesSettings(array $settings): array
    {
        $seriesSettings = $settings['series'] ?? null;

        if (! is_array($seriesSettings)) {
            return [];
        }

        $normalizedSeries = [];

        foreach ($seriesSettings as $series) {
            if (! is_array($series)) {
                continue;
            }

            $normalizedItem = [];

            foreach ($series as $key => $value) {
                if (is_string($key)) {
                    $normalizedItem[$key] = $value;
                }
            }

            $normalizedSeries[] = $normalizedItem;
        }

        return $normalizedSeries;
    }
}
