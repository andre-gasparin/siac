<?php

namespace App\Features\Reports\Agents\Contracts;

use App\Models\Team;

interface ReportAgent
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{
     *     message: string,
     *     suggestion_document: array<string, mixed>|null,
     *     suggestion_preview: string|null,
     *     activity: list<array{tool: string, label: string}>
     * }
     */
    public function respond(Team $team, int $systemId, string $date, string $currentText, array $messages): array;
}
