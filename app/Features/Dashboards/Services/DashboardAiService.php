<?php

namespace App\Features\Dashboards\Services;

use App\Infrastructure\AI\GeminiService;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class DashboardAiService
{
    public function __construct(
        private GeminiService $gemini,
        private DashboardSuggestionFormatter $formatter,
        private DashboardSuggestionFallback $fallback,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generateDashboardSuggestions(Team $team, ?string $objective = null): array
    {
        $systems = MonitoredSystem::query()
            ->where('team_id', $team->id)
            ->with(['parameters' => function ($query) {
                $query->where('is_active', true)
                    ->select(['id', 'monitored_system_id', 'name', 'unit']);
            }])
            ->get();

        $parameters = Parameter::query()
            ->whereHas('monitoredSystem', fn ($query) => $query->where('team_id', $team->id))
            ->where('is_active', true)
            ->get(['id', 'monitored_system_id', 'name', 'unit']);

        if ($this->gemini->isAvailable()) {
            try {
                $suggestions = $this->generateWithGemini($team, $systems, $parameters, $objective);

                if (count($suggestions) === 3) {
                    return $suggestions;
                }
            } catch (\Throwable $exception) {
                Log::warning('DashboardAiService Gemini call failed: '.$exception->getMessage());
            }
        }

        return $this->fallback->generate($team, $systems, $parameters, $objective);
    }

    /**
     * Maintained as the service's public formatting boundary.
     *
     * @param  array<int, mixed>  $suggestions
     * @param  Collection<int, Parameter>|null  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function formatSuggestions(array $suggestions, ?Collection $parameters = null): array
    {
        return $this->formatter->format($suggestions, $parameters ?? new Collection);
    }

    /**
     * @param  Collection<int, MonitoredSystem>  $systems
     * @param  Collection<int, Parameter>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function generateWithGemini(
        Team $team,
        Collection $systems,
        Collection $parameters,
        ?string $objective
    ): array {
        $systemsText = $systems->map(function (MonitoredSystem $system): string {
            $parametersText = $system->parameters
                ->map(fn (Parameter $parameter): string => "ID: {$parameter->id} => '{$parameter->name}' ({$parameter->unit})")
                ->implode('; ');

            return "- Sistema '{$system->name}' (ID: {$system->id}): [".($parametersText ?: 'Nenhum').']';
        })->implode("\n");

        if ($systemsText === '') {
            $systemsText = 'Nenhum sistema cadastrado ainda.';
        }

        $prompt = <<<PROMPT
Você é um engenheiro sênior de dados operacionais e especialista em dashboards industriais.
Analise a lista de sistemas e parâmetros reais do time "{$team->name}":
{$systemsText}

Objetivo do Usuário: "{$objective}"

Crie EXATAMENTE 3 opções de dashboards objetivas e úteis para operação.
Retorne um objeto JSON contendo uma chave "suggestions" com exatamente 3 elementos.

Cada sugestão deve conter title, description, component_count_label e components.
Cada componente deve ser um destes formatos:
- indicator: type, title, parameter_id e aggregation (last, avg, max, min ou sum);
- chart: type, title, chart_type (line ou bar) e series;
- text: type, title e content.

REGRAS CRÍTICAS:
1. Cada sugestão deve conter no máximo 4 indicadores.
2. Use no máximo 2 componentes de texto e inclua-os somente quando acrescentarem contexto operacional relevante.
3. Todo gráfico deve conter entre 1 e 3 séries.
4. Use somente IDs numéricos de parâmetros listados acima.
5. Séries podem conter label, chart_type, color hexadecimal, stroke_width, independent_axis e axis_position.
6. Quando houver eixos independentes, use left na primeira série e right na segunda.
7. component_count_label deve refletir a contagem real.
8. Responda apenas JSON puro válido.
9. As legendas das séries devem usar nomes claros e específicos dos parâmetros; nunca use títulos genéricos como "Série 1", "Valor" ou "Dados".
PROMPT;

        $parsed = $this->gemini->generateJson($prompt, ['timeout' => 25]);

        if (isset($parsed['suggestions']) && is_array($parsed['suggestions'])) {
            return $this->formatter->format(array_values($parsed['suggestions']), $parameters);
        }

        if (is_array($parsed) && count($parsed) === 3) {
            return $this->formatter->format(array_values($parsed), $parameters);
        }

        return [];
    }
}
