<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property int $monitored_system_id
 * @property Carbon $collected_at
 * @property Carbon $collected_date
 * @property string $status
 * @property int $saved_values_count
 * @property array<int>|null $parameter_ids
 * @property string|null $comment
 * @property Carbon|null $reverted_at
 * @property int|null $reverted_by
 * @property array<string, mixed>|null $snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read User|null $revertedBy
 * @property-read MonitoredSystem $monitoredSystem
 */
#[Fillable([
    'team_id',
    'user_id',
    'monitored_system_id',
    'collected_at',
    'collected_date',
    'status',
    'saved_values_count',
    'parameter_ids',
    'comment',
    'reverted_at',
    'reverted_by',
    'snapshot',
])]
class DataEntryBatch extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the team that owns the batch.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who submitted the batch.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who reverted the batch.
     *
     * @return BelongsTo<User, $this>
     */
    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    /**
     * Get the monitored system associated with this batch.
     *
     * @return BelongsTo<MonitoredSystem, $this>
     */
    public function monitoredSystem(): BelongsTo
    {
        return $this->belongsTo(MonitoredSystem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'collected_date' => 'date',
            'reverted_at' => 'datetime',
            'saved_values_count' => 'integer',
            'parameter_ids' => 'array',
            'snapshot' => 'array',
        ];
    }
}
