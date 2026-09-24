<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Models\SpreadsheetTemplate;
use App\Models\User;

class UpdateSpreadsheetTemplateAction
{
    /**
     * @param array{
     *     name?: string,
     *     description?: string|null,
     *     config?: array<string, mixed>,
     *     is_active?: bool,
     * } $data
     */
    public function execute(SpreadsheetTemplate $template, User $user, array $data): SpreadsheetTemplate
    {
        $payload = array_filter([
            'name' => $data['name'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
            'config' => $data['config'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'updated_by' => $user->id,
        ], fn ($val) => $val !== null);

        if (array_key_exists('description', $data) && $data['description'] === null) {
            $payload['description'] = null;
        }

        $template->update($payload);

        return $template->fresh();
    }
}
