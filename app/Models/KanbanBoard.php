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
 * @property string $name
 * @property string|null $description
 * @property int $order
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, KanbanColumn> $columns
 * @property-read Collection<int, KanbanTask> $tasks
 */
#[Fillable([
    'name',
    'description',
    'order',
    'created_by',
])]
class KanbanBoard extends Model
{
    use HasFactory;

    /**
     * Get the user who created the board.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the columns of this board.
     *
     * @return HasMany<KanbanColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class)->orderBy('order');
    }

    /**
     * Get all tasks of this board.
     *
     * @return HasMany<KanbanTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(KanbanTask::class);
    }
}
