<?php

namespace App\Features\StatisticalAnalysis\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StatisticalAnalysisDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $finiteNumber = static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_numeric($value) && ! is_finite((float) $value)) {
                $fail('O campo :attribute deve ser um número finito.');
            }
        };

        return [
            'system_id' => ['required', 'integer'],
            'parameter_ids' => ['required', 'array', 'min:1'],
            'parameter_ids.*' => ['integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'lookback_value' => ['nullable', 'integer', 'min:1', 'max:365'],
            'lookback_unit' => ['nullable', 'in:days,samples'],
            'frequency' => ['nullable', 'in:raw,daily_avg'],
            'num_alert_levels' => ['nullable', 'integer', 'min:1', 'max:3'],
            'ewma_lambda' => ['nullable', 'numeric', 'between:0.01,1.0'],
            'ewma_levels' => ['nullable', 'array'],
            'ewma_levels.*' => ['numeric', 'min:0.1', $finiteNumber],
            'cusum_k' => ['nullable', 'numeric', 'between:0.01,5.0'],
            'cusum_levels' => ['nullable', 'array'],
            'cusum_levels.*' => ['numeric', 'min:0.5', $finiteNumber],
            'manual_overrides' => ['nullable', 'array'],
            'manual_overrides.*' => ['nullable', 'array:mean,std_dev'],
            'manual_overrides.*.mean' => ['nullable', 'numeric', $finiteNumber],
            'manual_overrides.*.std_dev' => ['nullable', 'numeric', 'gt:0', $finiteNumber],
        ];
    }

    /**
     * @return array{
     *     system_id: int,
     *     parameter_ids: list<int>,
     *     start_date: string,
     *     end_date: string,
     *     lookback_value: int,
     *     lookback_unit: string,
     *     frequency: string,
     *     num_alert_levels: int,
     *     ewma_lambda: float,
     *     ewma_levels: list<float>,
     *     cusum_k: float,
     *     cusum_levels: list<float>,
     *     manual_overrides: array<int, array{mean: float|null, std_dev: float|null}>
     * }
     */
    public function filters(): array
    {
        $input = $this->safe();
        $validated = $input->all();
        $manualOverrides = [];

        foreach ($input->array('manual_overrides') as $parameterId => $override) {
            if (! is_array($override)) {
                continue;
            }

            $manualOverrides[(int) $parameterId] = [
                'mean' => isset($override['mean']) ? (float) $override['mean'] : null,
                'std_dev' => isset($override['std_dev']) ? (float) $override['std_dev'] : null,
            ];
        }

        return [
            'system_id' => $input->integer('system_id'),
            'parameter_ids' => array_values(array_map('intval', $input->array('parameter_ids'))),
            'start_date' => $input->string('start_date')->toString(),
            'end_date' => $input->string('end_date')->toString(),
            'lookback_value' => (int) ($validated['lookback_value'] ?? 30),
            'lookback_unit' => (string) ($validated['lookback_unit'] ?? 'days'),
            'frequency' => (string) ($validated['frequency'] ?? 'raw'),
            'num_alert_levels' => (int) ($validated['num_alert_levels'] ?? 3),
            'ewma_lambda' => (float) ($validated['ewma_lambda'] ?? 0.20),
            'ewma_levels' => array_values(array_map('floatval', $input->array('ewma_levels'))),
            'cusum_k' => (float) ($validated['cusum_k'] ?? 0.50),
            'cusum_levels' => array_values(array_map('floatval', $input->array('cusum_levels'))),
            'manual_overrides' => $manualOverrides,
        ];
    }
}
