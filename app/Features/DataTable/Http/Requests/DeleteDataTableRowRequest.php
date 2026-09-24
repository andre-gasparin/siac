<?php

namespace App\Features\DataTable\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteDataTableRowRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'timestamp' => ['required', 'date'],
            'parameter_ids' => ['required', 'array', 'min:1'],
            'parameter_ids.*' => ['integer'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['message' => 'Dados inválidos para exclusão.'], 422),
        );
    }
}
