<?php

namespace App\Features\SpreadsheetImports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpreadsheetEmailRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'spreadsheet_template_id' => ['required', 'integer', 'exists:spreadsheet_templates,id'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer'],
            'subject_operator' => ['nullable', 'string', 'in:contains,equals'],
            'subject_value' => ['nullable', 'string', 'max:255'],
            'body_operator' => ['nullable', 'string', 'in:contains'],
            'body_value' => ['nullable', 'string', 'max:255'],
            'sender_operator' => ['nullable', 'string', 'in:contains,equals'],
            'sender_value' => ['nullable', 'string', 'max:255'],
            'attachment_name_operator' => ['nullable', 'string', 'in:contains'],
            'attachment_name_value' => ['nullable', 'string', 'max:255'],
            'date_extraction_source' => ['nullable', 'string', 'in:auto,filename,subject,body,spreadsheet'],
            'date_extraction_pattern' => ['nullable', 'string', 'max:255'],
        ];
    }
}
