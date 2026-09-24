<?php

namespace App\Features\Dashboards\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDashboardFromProposalRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'is_public' => ['boolean'],
            'components' => ['required', 'array'],
            'components.*.type' => ['required', 'string', 'in:indicator,text,chart'],
            'components.*.grid_config' => ['required', 'array'],
            'components.*.settings' => ['nullable', 'array'],
        ];
    }
}
