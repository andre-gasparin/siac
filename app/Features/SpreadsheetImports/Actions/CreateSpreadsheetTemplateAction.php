<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use App\Models\User;

class CreateSpreadsheetTemplateAction
{
    /**
     * @param array{
     *     name: string,
     *     description?: string|null,
     *     config: array<string, mixed>,
     *     is_active?: bool,
     * } $data
     */
    public function execute(Team $team, User $user, array $data): SpreadsheetTemplate
    {
        return SpreadsheetTemplate::create([
            'team_id' => $team->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'config' => $data['config'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
