<?php

namespace App\Features\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'is_completed' => ['sometimes', 'required', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
