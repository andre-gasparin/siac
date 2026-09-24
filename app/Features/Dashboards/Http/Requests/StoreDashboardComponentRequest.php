<?php

namespace App\Features\Dashboards\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardComponentRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:indicator,text,chart'],
            'grid_config' => ['required', 'array'],
            'grid_config.x' => ['required', 'integer', 'min:0'],
            'grid_config.y' => ['required', 'integer', 'min:0'],
            'grid_config.w' => ['required', 'integer', 'min:1'],
            'grid_config.h' => ['required', 'integer', 'min:1'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
