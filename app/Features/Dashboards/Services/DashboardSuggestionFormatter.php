<?php

namespace App\Features\Dashboards\Services;

use App\Models\Parameter;
use Illuminate\Database\Eloquent\Collection;

class DashboardSuggestionFormatter
{
    /**
     * @var array<int, string>
     */
    private array $colors = [
        '#3B82F6',
        '#EF4444',
        '#10B981',
        '#F59E0B',
        '#8B5CF6',
        '#EC4899',
        '#06B6D4',
        '#F97316',
    ];

    /**
     * @param  array<int, mixed>  $suggestions
     * @param  Collection<int, Parameter>  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function format(array $suggestions, Collection $parameters): array
    {
        $formatted = [];

        foreach ($suggestions as $index => $suggestion) {
            $components = $suggestion['components'] ?? [];

            if ($components === []) {
                continue;
            }

            $indicators = array_slice(array_values(array_filter($components, fn (array $component): bool => ($component['type'] ?? 'indicator') === 'indicator')), 0, 4);
            $charts = array_values(array_filter($components, fn (array $component): bool => ($component['type'] ?? '') === 'chart'));
            $texts = array_slice(array_values(array_filter($components, fn (array $component): bool => ($component['type'] ?? '') === 'text')), 0, 2);
            $processed = [];
            $currentY = 20;

            foreach ($indicators as $componentIndex => $component) {
                $parameter = $this->resolveParameter($parameters, $component['parameter_id'] ?? null, $componentIndex);
                $title = $component['title'] ?? ($parameter instanceof Parameter ? $parameter->name : 'Indicador');

                $processed[] = [
                    'type' => 'indicator',
                    'title' => $title,
                    'grid_config' => [
                        'x' => ($componentIndex % 3) * 380 + 20,
                        'y' => $currentY + (int) floor($componentIndex / 3) * 220,
                        'w' => 340,
                        'h' => 200,
                    ],
                    'settings' => [
                        'title' => $title,
                        'parameter_id' => $parameter?->id,
                        'aggregation' => $component['aggregation'] ?? 'avg',
                    ],
                ];
            }

            if ($indicators !== []) {
                $currentY += (int) ceil(count($indicators) / 3) * 220 + 20;
            }

            foreach ($charts as $componentIndex => $component) {
                $series = $this->formatSeries($component['series'] ?? [], $parameters, $componentIndex);
                $title = $component['title'] ?? 'Gráfico de Desempenho';

                $processed[] = [
                    'type' => 'chart',
                    'title' => $title,
                    'grid_config' => [
                        'x' => ($componentIndex % 2) * 590 + 20,
                        'y' => $currentY + (int) floor($componentIndex / 2) * 380,
                        'w' => 560,
                        'h' => 360,
                    ],
                    'settings' => [
                        'title' => $title,
                        'chart_type' => $component['chart_type'] ?? 'line',
                        'series' => $series,
                    ],
                ];
            }

            if ($charts !== []) {
                $currentY += (int) ceil(count($charts) / 2) * 380 + 20;
            }

            foreach ($texts as $componentIndex => $component) {
                $title = $component['title'] ?? 'Notas Operacionais';
                $processed[] = [
                    'type' => 'text',
                    'title' => $title,
                    'grid_config' => [
                        'x' => ($componentIndex % 2) * 590 + 20,
                        'y' => $currentY + (int) floor($componentIndex / 2) * 220,
                        'w' => 560,
                        'h' => 200,
                    ],
                    'settings' => [
                        'title' => $title,
                        'content' => $component['content'] ?? 'Manter acompanhamento constante das medições.',
                    ],
                ];
            }

            $count = count($processed);
            $formatted[] = [
                'id' => 'suggestion-'.($index + 1),
                'title' => $suggestion['title'] ?? 'Dashboard Sugerido '.($index + 1),
                'description' => $suggestion['description'] ?? 'Dashboard gerado com apoio de IA.',
                'component_count_label' => "{$count} componentes configuráveis",
                'components' => $processed,
            ];
        }

        return array_slice($formatted, 0, 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $series
     * @param  Collection<int, Parameter>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function formatSeries(array $series, Collection $parameters, int $componentIndex): array
    {
        if ($series === [] && $parameters->isNotEmpty()) {
            $first = $parameters->get(($componentIndex * 2) % $parameters->count());
            $second = $parameters->get(($componentIndex * 2 + 1) % $parameters->count());
            $series = [['parameter_id' => $first?->id]];

            if ($second && $second->id !== $first?->id) {
                $series[] = ['parameter_id' => $second->id];
            }
        }

        $formatted = [];

        foreach ($series as $seriesIndex => $item) {
            $parameter = $this->resolveParameter(
                $parameters,
                $item['parameter_id'] ?? null,
                $componentIndex + $seriesIndex,
            );

            $formatted[] = [
                'parameter_id' => $parameter?->id,
                'label' => $this->seriesLabel($parameter, $seriesIndex),
                'chart_type' => $item['chart_type'] ?? 'line',
                'color' => $item['color'] ?? $this->colors[($componentIndex * 2 + $seriesIndex) % count($this->colors)],
                'stroke_width' => (int) ($item['stroke_width'] ?? 2),
                'independent_axis' => (bool) ($item['independent_axis'] ?? ($seriesIndex > 0)),
                'axis_position' => $item['axis_position'] ?? ($seriesIndex === 1 ? 'right' : 'left'),
            ];
        }

        return $formatted;
    }

    private function seriesLabel(?Parameter $parameter, int $seriesIndex): string
    {
        if (! $parameter instanceof Parameter) {
            return 'Série '.($seriesIndex + 1);
        }

        $unit = trim((string) $parameter->unit);

        return $unit === '' ? $parameter->name : "{$parameter->name} ({$unit})";
    }

    /**
     * @param  Collection<int, Parameter>  $parameters
     */
    private function resolveParameter(Collection $parameters, mixed $parameterId, int $fallbackIndex): ?Parameter
    {
        $parameter = $parameters->firstWhere('id', $parameterId);

        if ($parameter instanceof Parameter || $parameters->isEmpty()) {
            return $parameter;
        }

        return $parameters->get($fallbackIndex % $parameters->count());
    }
}
