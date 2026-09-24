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
 * @property int $parameter_id
 * @property Carbon $measured_at
 * @property Carbon $measured_date
 * @property float|null $value
 * @property string $source_type
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read MonitoredSystem $monitoredSystem
 * @property-read Parameter $parameter
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
#[Fillable([
    'team_id',
    'monitored_system_id',
    'parameter_id',
    'measured_at',
    'measured_date',
    'value',
    'source_type',
    'created_by',
    'updated_by',
])]
class ParameterValue extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the team that owns this parameter value.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the monitored system associated with this value.
     *
     * @return BelongsTo<MonitoredSystem, $this>
     */
    public function monitoredSystem(): BelongsTo
    {
        return $this->belongsTo(MonitoredSystem::class);
    }

    /**
     * Get the parameter associated with this value.
     *
     * @return BelongsTo<Parameter, $this>
     */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }

    /**
     * Get the user who created this value.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this value.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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
            'value' => 'double',
        ];
    }
}
