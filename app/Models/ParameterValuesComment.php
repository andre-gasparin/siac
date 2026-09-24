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
 * @property int $monitored_system_id
 * @property Carbon $measured_at
 * @property Carbon $measured_date
 * @property string $comment
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read MonitoredSystem $monitoredSystem
 * @property-read User|null $creator
 */
#[Fillable([
    'team_id',
    'monitored_system_id',
    'measured_at',
    'measured_date',
    'comment',
    'created_by',
])]
class ParameterValuesComment extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'parameter_values_comments';

    /**
     * Get the team that owns the comment.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the monitored system associated with this comment.
     *
     * @return BelongsTo<MonitoredSystem, $this>
     */
    public function monitoredSystem(): BelongsTo
    {
        return $this->belongsTo(MonitoredSystem::class);
    }

    /**
     * Get the user who created this comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'measured_date' => 'date',
        ];
    }
}
