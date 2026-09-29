<?php

namespace App\Features\DataEntry\Http\Requests;

use App\Models\Team;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDataEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $requireResponsible = $team instanceof Team ? (bool) $team->require_data_entry_responsible : false;

        return [
            'responsible' => [$requireResponsible ? 'required' : 'nullable', 'string', 'max:255'],
            'monitored_system_id' => ['required', 'integer', 'exists:monitored_systems,id'],
            'collected_at' => ['required', 'date'],
            'values' => ['required', 'array'],
            'values.*.parameter_id' => ['required', 'integer', 'exists:parameters,id'],
            'values.*.value' => ['nullable', 'numeric'],
            'comment' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'responsible.required' => 'O campo Responsável é obrigatório para esta unidade.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Dados inválidos para a entrada de dados.',
                'errors' => $validator->errors(),
            ], 422),
        );
    }
}
