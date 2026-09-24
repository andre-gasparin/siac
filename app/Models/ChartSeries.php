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
 * @property int $chart_template_id
 * @property int $monitored_system_id
 * @property int $parameter_id
 * @property int $axis
 * @property int $sort_order
 * @property string|null $label
 * @property string|null $color
 * @property array<string, mixed>|null $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read ChartTemplate $chartTemplate
 * @property-read MonitoredSystem $monitoredSystem
 * @property-read Parameter $parameter
 */
#[Fillable([
    'team_id',
    'chart_template_id',
    'monitored_system_id',
    'parameter_id',
    'axis',
    'sort_order',
    'label',
    'color',
    'options',
])]
class ChartSeries extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the team that owns the chart series.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the chart template associated with this series.
     *
     * @return BelongsTo<ChartTemplate, $this>
     */
    public function chartTemplate(): BelongsTo
    {
        return $this->belongsTo(ChartTemplate::class);
    }

    /**
     * Get the monitored system associated with this series.
     *
     * @return BelongsTo<MonitoredSystem, $this>
     */
    public function monitoredSystem(): BelongsTo
    {
        return $this->belongsTo(MonitoredSystem::class);
    }

    /**
     * Get the parameter associated with this series.
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
            'axis' => 'integer',
            'sort_order' => 'integer',
            'options' => 'array',
        ];
    }
}
