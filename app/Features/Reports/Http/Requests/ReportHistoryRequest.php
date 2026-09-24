<?php

namespace App\Features\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportHistoryRequest extends FormRequest
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
            'system_id' => ['required', 'integer'],
            'before_date' => ['required', 'date_format:Y-m-d'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
