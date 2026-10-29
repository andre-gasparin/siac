<?php

namespace App\Features\DataTable\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BatchUpdateDataTableValuesRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cells' => ['required', 'array', 'min:1', 'max:5000'],
            'cells.*.parameter_id' => ['required', 'integer', 'exists:parameters,id'],
            'cells.*.timestamp' => ['required', 'date'],
            'value' => ['nullable', 'numeric'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Dados inválidos para atualização em lote.',
                'errors' => $validator->errors(),
            ], 422),
        );
    }
}
