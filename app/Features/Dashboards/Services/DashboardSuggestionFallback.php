<?php

namespace App\Features\Dashboards\Services;

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class DashboardSuggestionFallback
{
    public function __construct(private DashboardSuggestionFormatter $formatter) {}

    /**
     * @param  Collection<int, MonitoredSystem>  $systems
     * @param  Collection<int, Parameter>  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function generate(
        Team $team,
        Collection $systems,
        Collection $parameters,
        ?string $objective
    ): array {
        $objectiveTitle = $objective ? mb_convert_case($objective, MB_CASE_TITLE, 'UTF-8') : null;
        $firstSystem = $systems->first();
        $systemName = $firstSystem instanceof MonitoredSystem ? $firstSystem->name : 'Sistemas';
        $proposalDefinitions = [
            [
                'title' => $objectiveTitle ? "{$objectiveTitle} - Análise Operacional Consolidada" : "Análise Geral de {$systemName} e Caldeiras",
                'description' => 'Painel objetivo para monitoramento dos parâmetros operacionais mais relevantes.',
            ],
            [
                'title' => 'Entrada de Água & Tratamento Térmico Completo',
                'description' => 'Painel completo de controle hídrico, temperatura e balanço de massa.',
            ],
            [
                'title' => 'Painel Executivo de Desempenho & Operações',
                'description' => 'Visão estratégica e operacional com foco nos principais indicadores.',
            ],
        ];

        $suggestions = [];

        foreach ($proposalDefinitions as $proposalIndex => $definition) {
            $suggestions[] = [
                ...$definition,
                'components' => $this->components($parameters, $proposalIndex),
            ];
        }

        return $this->formatter->format($suggestions, $parameters);
    }

    /**
     * @param  Collection<int, Parameter>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function components(Collection $parameters, int $proposalIndex): array
    {
        $components = [];
        $aggregations = ['avg', 'last', 'max', 'min', 'sum'];
        $parameterCount = max(1, $parameters->count());

        for ($index = 0; $index < 4; $index++) {
            $parameter = $parameters->get(($proposalIndex * 4 + $index) % $parameterCount);
            $aggregation = $aggregations[$index % count($aggregations)];
            $components[] = [
                'type' => 'indicator',
                'title' => ($parameter instanceof Parameter ? $parameter->name : 'Indicador '.($index + 1)).' ('.strtoupper($aggregation).')',
                'parameter_id' => $parameter?->id,
                'aggregation' => $aggregation,
            ];
        }

        for ($index = 0; $index < 4; $index++) {
            $first = $parameters->get(($proposalIndex * 4 + $index) % $parameterCount);
            $second = $parameters->get(($proposalIndex * 4 + $index + 1) % $parameterCount);
            $series = [['parameter_id' => $first?->id]];

            if ($second && $second->id !== $first?->id) {
                $series[] = [
                    'parameter_id' => $second->id,
                    'chart_type' => $index % 2 === 0 ? 'line' : 'bar',
                    'independent_axis' => true,
                    'axis_position' => 'right',
                ];
            }

            $components[] = [
                'type' => 'chart',
                'title' => 'Gráfico '.($index + 1).': '.($first instanceof Parameter ? $first->name : 'Desempenho'),
                'series' => $series,
            ];
        }

        return [
            ...$components,
            ['type' => 'text', 'title' => 'Diretrizes & Metas de Operação', 'content' => 'Manter atenção aos parâmetros críticos para assegurar a eficiência operacional.'],
        ];
    }
}
