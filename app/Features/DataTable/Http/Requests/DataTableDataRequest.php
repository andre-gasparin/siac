<?php

namespace App\Features\DataTable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DataTableDataRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('system_ids'))) {
            $this->merge([
                'system_ids' => explode(',', $this->input('system_ids')),
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'system_ids' => ['nullable', 'array'],
            'system_ids.*' => ['integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
    }
}
