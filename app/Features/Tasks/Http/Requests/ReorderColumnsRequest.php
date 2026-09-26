<?php

namespace App\Features\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderColumnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ordered_column_ids' => ['required', 'array'],
            'ordered_column_ids.*' => ['integer', 'exists:kanban_columns,id'],
        ];
    }
}
