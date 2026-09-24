<?php

namespace App\Features\DataTable\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDataTableValueRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'parameter_id' => ['required', 'integer', 'exists:parameters,id'],
            'timestamp' => ['required', 'date'],
            'value' => ['nullable', 'numeric'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Dados inválidos para atualização do valor.',
                'errors' => $validator->errors(),
            ], 422),
        );
    }
}
