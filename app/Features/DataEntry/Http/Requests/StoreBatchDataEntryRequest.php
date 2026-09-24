<?php

namespace App\Features\DataEntry\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBatchDataEntryRequest extends FormRequest
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
        return [
            'collected_at' => ['required', 'date'],
            'systems' => ['required', 'array', 'min:1'],
            'systems.*.monitored_system_id' => ['required', 'integer', 'exists:monitored_systems,id'],
            'systems.*.values' => ['present', 'array'],
            'systems.*.values.*.parameter_id' => ['required', 'integer', 'exists:parameters,id'],
            'systems.*.values.*.value' => ['nullable', 'numeric'],
            'systems.*.comment' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Dados inválidos para a entrada de dados em lote.',
                'errors' => $validator->errors(),
            ], 422),
        );
    }
}
