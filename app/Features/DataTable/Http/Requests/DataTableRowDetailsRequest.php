<?php

namespace App\Features\DataTable\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DataTableRowDetailsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');
        $systemRule = Rule::exists('monitored_systems', 'id')->where('team_id', $team->id)->whereNull('deleted_at');

        return [
            'timestamp' => ['required', 'date'],
            'system_ids' => [$this->isMethod('GET') ? 'required' : 'sometimes', 'array', 'min:1'],
            'system_ids.*' => ['required', 'integer', $systemRule],
            'monitored_system_id' => [$this->isMethod('PUT') ? 'required' : 'sometimes', 'integer', $systemRule],
            'comment' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
