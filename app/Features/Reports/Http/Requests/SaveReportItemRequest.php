<?php

namespace App\Features\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveReportItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'date_reference' => ['required', 'date_format:Y-m-d'],
            'system_id' => ['required', 'integer'],
            'document' => ['required', 'array'],
            'document.type' => ['required', 'string', 'in:doc'],
            'document.content' => ['nullable', 'array'],
            'html' => ['required', 'string', 'max:1000000'],
            'hide_data' => ['required', 'boolean'],
            'show_data_results' => ['sometimes', 'boolean'],
            'is_stopped' => ['required', 'boolean'],
            'expected_updated_at' => ['nullable', 'date'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
