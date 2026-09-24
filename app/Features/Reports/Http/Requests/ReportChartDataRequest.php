<?php

namespace App\Features\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportChartDataRequest extends FormRequest
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
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'series' => ['required', 'array', 'min:1', 'max:5'],
            'series.*.parameter_id' => ['required', 'integer'],
            'series.*.label' => ['nullable', 'string', 'max:255'],
            'series.*.chart_type' => ['required', 'string', 'in:line,bar,area'],
            'series.*.color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'series.*.stroke_width' => ['required', 'integer', 'between:1,10'],
            'series.*.axis_position' => ['required', 'string', 'in:left,right'],
            'series.*.min_val' => ['nullable', 'numeric'],
            'series.*.max_val' => ['nullable', 'numeric'],
        ];
    }
}
