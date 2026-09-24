<?php

namespace App\Features\Reports\Agents\Support;

use App\Features\Reports\Agents\Contracts\AgentTool;
use App\Features\Reports\Agents\Tools\GetOperationalMetricsTool;
use App\Features\Reports\Agents\Tools\SearchCatalogTool;
use App\Features\Reports\Agents\Tools\SearchPreviousCommentsTool;
use App\Features\Reports\Agents\Tools\SearchPreviousReportsTool;
use App\Models\Team;
use Illuminate\Validation\ValidationException;

class ReportAgentToolRegistry
{
    /** @var array<string, AgentTool> */
    private array $tools;

    public function __construct(
        SearchCatalogTool $catalog,
        GetOperationalMetricsTool $metrics,
        SearchPreviousCommentsTool $comments,
        SearchPreviousReportsTool $reports,
    ) {
        $this->tools = collect([$catalog, $metrics, $comments, $reports])
            ->mapWithKeys(fn (AgentTool $tool): array => [$tool->name() => $tool])
            ->all();
    }

    /**
     * @return list<array{name: string, description: string, arguments: array<string, string>}>
     */
    public function catalog(): array
    {
        return array_values(array_map(
            fn (AgentTool $tool): array => $tool->definition(),
            $this->tools,
        ));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{result: mixed, activity: string}
     */
    public function execute(Team $team, string $name, array $arguments): array
    {
        $tool = $this->tools[$name] ?? null;

        if (! $tool) {
            throw ValidationException::withMessages([
                'tool_calls' => "A ferramenta {$name} não é permitida.",
            ]);
        }

        return $tool->execute($team, $arguments);
    }
}
