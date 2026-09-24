<?php

namespace App\Features\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'date_reference' => ['required', 'date_format:Y-m-d'],
            'system_id' => ['required', 'integer'],
            'current_text' => ['nullable', 'string', 'max:50000'],
            'messages' => ['nullable', 'array', 'max:20'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:5000'],
        ];
    }
}
