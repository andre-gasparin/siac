<?php

namespace App\Features\SpreadsheetImports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnqueuePendingConfirmationRequest extends FormRequest
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
            'spreadsheet_template_id' => ['nullable', 'integer', 'exists:spreadsheet_templates,id'],
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
