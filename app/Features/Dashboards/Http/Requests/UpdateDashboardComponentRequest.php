<?php

namespace App\Features\Dashboards\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardComponentRequest extends FormRequest
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
            'settings' => ['required', 'array'],
            'grid_config' => ['nullable', 'array'],
        ];
    }
}
