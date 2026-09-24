<?php

namespace App\Features\Reports\Agents\Contracts;

use App\Models\Team;

interface AgentTool
{
    public function name(): string;

    /**
     * @return array{name: string, description: string, arguments: array<string, string>}
     */
    public function definition(): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{result: mixed, activity: string}
     */
    public function execute(Team $team, array $arguments): array;
}
