<?php

namespace App\Features\Teams\Http\Requests;

use App\Features\Teams\Enums\TeamRole;
use App\Features\Teams\Rules\UniqueTeamInvitation;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTeamInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueTeamInvitation($team)],
            'role' => ['required', 'string', Rule::enum(TeamRole::class)],
        ];
    }
}
