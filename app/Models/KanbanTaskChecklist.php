<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kanban_task_id
 * @property string $title
 * @property bool $is_completed
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KanbanTask $task
 */
#[Fillable([
    'kanban_task_id',
    'title',
    'is_completed',
    'order',
])]
class KanbanTaskChecklist extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }

    /**
     * Get the task that owns this checklist item.
     *
     * @return BelongsTo<KanbanTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(KanbanTask::class, 'kanban_task_id');
    }
}
