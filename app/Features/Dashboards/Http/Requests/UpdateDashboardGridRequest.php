<?php

namespace App\Features\Dashboards\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardGridRequest extends FormRequest
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
            'components' => ['required', 'array'],
            'components.*.id' => ['required', 'integer', 'exists:dashboard_components,id'],
            'components.*.grid_config' => ['required', 'array'],
            'components.*.grid_config.x' => ['required', 'integer', 'min:0'],
            'components.*.grid_config.y' => ['required', 'integer', 'min:0'],
            'components.*.grid_config.w' => ['required', 'integer', 'min:1'],
            'components.*.grid_config.h' => ['required', 'integer', 'min:1'],
        ];
    }
}
