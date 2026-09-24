<?php

namespace App\Features\SpreadsheetImports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteSpreadsheetImportRequest extends FormRequest
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
            'file_name' => ['required', 'string', 'max:255'],
            'file_path' => ['nullable', 'string', 'max:500'],
            'reference_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.system_id' => ['required', 'integer'],
            'items.*.parameter_id' => ['required', 'integer'],
            'items.*.final_value' => ['nullable', 'numeric'],
            'items.*.measured_at' => ['required', 'string'],
            'items.*.measured_date' => ['required', 'string'],
        ];
    }
}
