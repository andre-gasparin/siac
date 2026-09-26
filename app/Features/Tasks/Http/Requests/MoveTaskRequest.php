<?php

namespace App\Features\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveTaskRequest extends FormRequest
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
            'to_column_id' => ['required', 'integer', 'exists:kanban_columns,id'],
            'new_order' => ['required', 'integer', 'min:0'],
            'reordered_task_ids' => ['nullable', 'array'],
            'reordered_task_ids.*' => ['integer', 'exists:kanban_tasks,id'],
        ];
    }
}
