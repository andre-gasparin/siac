<?php

namespace App\Features\Teams\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamSystemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'systems' => ['required', 'array'],
            'systems.*.id' => ['required', 'integer'],
            'systems.*.name' => ['required', 'string', 'max:255'],
            'systems.*.is_active' => ['required', 'boolean'],
            'systems.*.sort_order' => ['required', 'integer'],
            'systems.*.parameters' => ['nullable', 'array'],
            'systems.*.parameters.*.id' => ['required', 'integer'],
            'systems.*.parameters.*.name' => ['required', 'string', 'max:255'],
            'systems.*.parameters.*.code' => ['nullable', 'string', 'max:255'],
            'systems.*.parameters.*.tag' => ['nullable', 'string', 'max:255'],
            'systems.*.parameters.*.unit' => ['nullable', 'string', 'max:40'],
            'systems.*.parameters.*.decimals' => ['required', 'integer', 'min:0', 'max:10'],
            'systems.*.parameters.*.sort_order' => ['required', 'integer'],
            'systems.*.parameters.*.is_active' => ['required', 'boolean'],
            'systems.*.parameters.*.alert_1_min' => ['nullable', 'numeric'],
            'systems.*.parameters.*.alert_1_max' => ['nullable', 'numeric'],
            'systems.*.parameters.*.alert_2_min' => ['nullable', 'numeric'],
            'systems.*.parameters.*.alert_2_max' => ['nullable', 'numeric'],
            'systems.*.parameters.*.alert_3_min' => ['nullable', 'numeric'],
            'systems.*.parameters.*.alert_3_max' => ['nullable', 'numeric'],
            'systems.*.parameters.*.alert_4_min' => ['nullable', 'numeric'],
            'systems.*.parameters.*.alert_4_max' => ['nullable', 'numeric'],
        ];
    }
}
