<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kanban_board_id
 * @property int $kanban_column_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_date
 * @property bool $is_completed
 * @property Carbon|null $completed_at
 * @property int|null $assigned_to_user_id
 * @property int|null $created_by
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KanbanBoard $board
 * @property-read KanbanColumn $column
 * @property-read User|null $assignedUser
 * @property-read User|null $creator
 * @property-read Collection<int, KanbanTaskChecklist> $checklists
 * @property-read Collection<int, KanbanTaskAttachment> $attachments
 */
#[Fillable([
    'kanban_board_id',
    'kanban_column_id',
    'title',
    'description',
    'due_date',
    'is_completed',
    'completed_at',
    'assigned_to_user_id',
    'created_by',
    'order',
])]
class KanbanTask extends Model
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
            'due_date' => 'date:Y-m-d',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the board that owns this task.
     *
     * @return BelongsTo<KanbanBoard, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'kanban_board_id');
    }

    /**
     * Get the column that owns this task.
     *
     * @return BelongsTo<KanbanColumn, $this>
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(KanbanColumn::class, 'kanban_column_id');
    }

    /**
     * Get the user assigned to this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the user who created this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the checklist items for this task.
     *
     * @return HasMany<KanbanTaskChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(KanbanTaskChecklist::class, 'kanban_task_id')->orderBy('order');
    }

    /**
     * Get the attachments for this task.
     *
     * @return HasMany<KanbanTaskAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(KanbanTaskAttachment::class, 'kanban_task_id')->latest();
    }
}
