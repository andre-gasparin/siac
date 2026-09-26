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
 * @property string $name
 * @property string|null $color
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KanbanBoard $board
 * @property-read Collection<int, KanbanTask> $tasks
 */
#[Fillable([
    'kanban_board_id',
    'name',
    'color',
    'order',
])]
class KanbanColumn extends Model
{
    use HasFactory;

    /**
     * Get the board that owns this column.
     *
     * @return BelongsTo<KanbanBoard, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'kanban_board_id');
    }

    /**
     * Get the tasks in this column.
     *
     * @return HasMany<KanbanTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(KanbanTask::class, 'kanban_column_id')->orderBy('order');
    }
}
