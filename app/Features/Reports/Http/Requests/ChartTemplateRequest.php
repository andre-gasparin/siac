<?php

namespace App\Features\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChartTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_favorite' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.height' => ['nullable', 'integer', 'between:100,1000'],
            'series' => ['required', 'array', 'min:1', 'max:5'],
            'series.*.parameter_id' => ['required', 'integer', 'exists:parameters,id'],
            'series.*.label' => ['nullable', 'string', 'max:255'],
            'series.*.chart_type' => ['required', 'string', 'in:line,bar,area'],
            'series.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'series.*.stroke_width' => ['nullable', 'integer', 'between:1,10'],
            'series.*.axis_position' => ['nullable', 'string', 'in:left,right'],
            'series.*.min_val' => ['nullable', 'numeric'],
            'series.*.max_val' => ['nullable', 'numeric'],
            'series.*.show_points' => ['nullable', 'boolean'],
            'series.*.show_values' => ['nullable', 'boolean'],
        ];
    }
}
