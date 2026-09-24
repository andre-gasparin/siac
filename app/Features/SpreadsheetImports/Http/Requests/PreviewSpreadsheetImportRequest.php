<?php

namespace App\Features\SpreadsheetImports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewSpreadsheetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'spreadsheet_template_id' => ['required', 'integer', 'exists:spreadsheet_templates,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'], // max 20MB
            'reference_date' => ['nullable', 'date'],
            'date_scope' => ['nullable', 'string', 'in:single,all'],
        ];
    }
}
