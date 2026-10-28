<?php

namespace App\Features\Calculations\Http\Requests;

use App\Features\Calculations\Services\ParameterCalculationService;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');
        $rules = ['name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(array_keys(ParameterCalculationService::TYPES))]];
        foreach (['primary', 'secondary', 'destination'] as $role) {
            $rules[$role.'_system_id'] = ['required', 'integer', Rule::exists('monitored_systems', 'id')->where('team_id', $team->id)->whereNull('deleted_at')];
            $rules[$role.'_parameter_id'] = ['required', 'integer', Rule::exists('parameters', 'id')->where('team_id', $team->id)
                ->where('monitored_system_id', $this->input($role.'_system_id'))->whereNull('deleted_at')];
        }
        $rules['destination_parameter_id'][] = 'different:primary_parameter_id';
        $rules['destination_parameter_id'][] = 'different:secondary_parameter_id';
        foreach (['primary', 'secondary', 'result'] as $role) {
            $rules[$role.'_multiplier'] = ['nullable', 'numeric', 'between:-1.0e100,1.0e100'];
        }

        return $rules;
    }
}
