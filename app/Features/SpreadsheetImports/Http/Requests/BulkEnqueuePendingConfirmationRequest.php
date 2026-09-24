<?php

namespace App\Features\SpreadsheetImports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkEnqueuePendingConfirmationRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:spreadsheet_email_inbox_items,id'],
        ];
    }
}
