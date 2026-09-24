<?php

namespace App\Features\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupReportItemsRequest extends FormRequest
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
            'report_id' => ['required', 'integer'],
            'item_ids' => ['required', 'array', 'min:2'],
            'item_ids.*' => ['integer', 'distinct'],
            'source_item_id' => ['required', 'integer'],
        ];
    }
}
