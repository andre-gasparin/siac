<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Models\SpreadsheetTemplate;

class DeleteSpreadsheetTemplateAction
{
    public function execute(SpreadsheetTemplate $template): bool
    {
        return (bool) $template->delete();
    }
}
