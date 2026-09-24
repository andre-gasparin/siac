<?php

namespace App\Features\Reports\Agents\Tools;

use App\Features\Reports\Agents\Contracts\AgentTool;
use App\Features\Reports\Agents\Support\ReportDataQuery;
use App\Models\Team;

class SearchCatalogTool implements AgentTool
{
    public function __construct(private ReportDataQuery $query) {}

    public function name(): string
    {
        return 'search_catalog';
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Busca sistemas e parâmetros ativos da empresa atual.',
            'arguments' => ['query' => 'texto opcional', 'limit' => '1 a 20'],
        ];
    }

    public function execute(Team $team, array $arguments): array
    {
        return $this->query->searchCatalog($team, $arguments);
    }
}
