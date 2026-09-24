<?php

namespace App\Features\Reports\Agents\Tools;

use App\Features\Reports\Agents\Contracts\AgentTool;
use App\Features\Reports\Agents\Support\ReportDataQuery;
use App\Models\Team;

class SearchPreviousCommentsTool implements AgentTool
{
    public function __construct(private ReportDataQuery $query) {}

    public function name(): string
    {
        return 'search_previous_comments';
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Busca comentários anteriores de um sistema.',
            'arguments' => [
                'system_id' => 'ID do sistema',
                'before_date' => 'YYYY-MM-DD',
                'query' => 'texto opcional',
                'limit' => '1 a 10',
            ],
        ];
    }

    public function execute(Team $team, array $arguments): array
    {
        return $this->query->previousComments($team, $arguments);
    }
}
