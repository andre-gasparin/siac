<?php

namespace App\Features\Reports\Agents\Tools;

use App\Features\Reports\Agents\Contracts\AgentTool;
use App\Features\Reports\Agents\Support\ReportDataQuery;
use App\Models\Team;

class GetOperationalMetricsTool implements AgentTool
{
    public function __construct(private ReportDataQuery $query) {}

    public function name(): string
    {
        return 'get_operational_metrics';
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Consulta médias, mínimos, máximos e quantidade de medições por período.',
            'arguments' => [
                'system_id' => 'ID do sistema',
                'parameter_ids' => 'IDs opcionais, máximo 20',
                'start_date' => 'YYYY-MM-DD',
                'end_date' => 'YYYY-MM-DD, período máximo 90 dias',
            ],
        ];
    }

    public function execute(Team $team, array $arguments): array
    {
        return $this->query->operationalMetrics($team, $arguments);
    }
}
