<?php

namespace App\Features\DataEntry\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewDataEntryEwmaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'collected_at' => ['required', 'date'],
            'lookback' => ['sometimes', 'integer', 'min:2', 'max:500'],
            'values' => ['required', 'array', 'max:500'],
            'values.*.parameter_id' => ['required', 'integer', 'distinct', Rule::exists('parameters', 'id')->where('team_id', $team->id)->where('is_active', true)->whereNull('deleted_at')],
            'values.*.value' => ['required', 'numeric'],
        ];
    }
}
