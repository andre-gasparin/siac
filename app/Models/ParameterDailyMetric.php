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
 * @property Carbon $measured_date
 * @property int $values_count
 * @property float|null $average_value
 * @property float|null $minimum_value
 * @property float|null $maximum_value
 * @property int $out_of_limit_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read MonitoredSystem $monitoredSystem
 * @property-read Parameter $parameter
 */
#[Fillable([
    'team_id',
    'monitored_system_id',
    'parameter_id',
    'measured_date',
    'values_count',
    'average_value',
    'minimum_value',
    'maximum_value',
    'out_of_limit_count',
])]
class ParameterDailyMetric extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the team that owns the daily metrics.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the monitored system associated with this metric.
     *
     * @return BelongsTo<MonitoredSystem, $this>
     */
    public function monitoredSystem(): BelongsTo
    {
        return $this->belongsTo(MonitoredSystem::class);
    }

    /**
     * Get the parameter associated with this metric.
     *
     * @return BelongsTo<Parameter, $this>
     */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_date' => 'date',
            'values_count' => 'integer',
            'average_value' => 'double',
            'minimum_value' => 'double',
            'maximum_value' => 'double',
            'out_of_limit_count' => 'integer',
        ];
    }
}
