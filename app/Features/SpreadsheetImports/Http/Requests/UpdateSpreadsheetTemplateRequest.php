<?php

namespace App\Features\SpreadsheetImports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpreadsheetTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'config' => ['sometimes', 'required', 'array'],
            'is_active' => ['boolean'],
        ];
    }
}
