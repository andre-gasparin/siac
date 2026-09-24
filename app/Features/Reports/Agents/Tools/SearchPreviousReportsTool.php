<?php

namespace App\Features\Reports\Agents\Tools;

use App\Features\Reports\Agents\Contracts\AgentTool;
use App\Features\Reports\Agents\Support\ReportDataQuery;
use App\Models\Team;

class SearchPreviousReportsTool implements AgentTool
{
    public function __construct(private ReportDataQuery $query) {}

    public function name(): string
    {
        return 'search_previous_reports';
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Busca relatórios anteriores da empresa por período ou texto.',
            'arguments' => [
                'start_date' => 'YYYY-MM-DD opcional',
                'end_date' => 'YYYY-MM-DD opcional',
                'query' => 'texto opcional',
                'limit' => '1 a 10',
            ],
        ];
    }

    public function execute(Team $team, array $arguments): array
    {
        return $this->query->previousReports($team, $arguments);
    }
}
