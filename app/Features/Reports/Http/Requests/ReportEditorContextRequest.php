<?php

namespace App\Features\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportEditorContextRequest extends FormRequest
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
        ];
    }
}
