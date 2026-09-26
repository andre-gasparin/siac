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
 * @property int|null $user_id
 * @property string $original_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KanbanTask $task
 * @property-read User|null $user
 */
#[Fillable([
    'kanban_task_id',
    'user_id',
    'original_name',
    'file_path',
    'mime_type',
    'size_bytes',
])]
class KanbanTaskAttachment extends Model
{
    use HasFactory;

    /**
     * Get the task that owns this attachment.
     *
     * @return BelongsTo<KanbanTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(KanbanTask::class, 'kanban_task_id');
    }

    /**
     * Get the user who uploaded this attachment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
