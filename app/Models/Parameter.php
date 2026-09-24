<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $monitored_system_id
 * @property string $name
 * @property string|null $code
 * @property string|null $tag
 * @property string|null $unit
 * @property int $decimals
 * @property int $sort_order
 * @property bool $is_active
 * @property float|null $alert_1_min
 * @property float|null $alert_1_max
 * @property float|null $alert_2_min
 * @property float|null $alert_2_max
 * @property float|null $alert_3_min
 * @property float|null $alert_3_max
 * @property float|null $alert_4_min
 * @property float|null $alert_4_max
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read MonitoredSystem|null $monitoredSystem
 * @property-read Collection<int, ParameterValue> $values
 * @property-read Collection<int, ParameterDailyMetric> $dailyMetrics
 * @property-read Collection<int, ReportItem> $reportItems
 * @property-read Collection<int, ChartSeries> $chartSeries
 */
#[Fillable([
    'team_id',
    'monitored_system_id',
    'name',
    'code',
    'tag',
    'unit',
    'decimals',
    'sort_order',
    'is_active',
    'alert_1_min',
    'alert_1_max',
    'alert_2_min',
    'alert_2_max',
    'alert_3_min',
    'alert_3_max',
    'alert_4_min',
    'alert_4_max',
])]
class Parameter extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, SoftDeletes;

    /**
     * Get the team that owns the parameter.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the monitored system associated with the parameter.
     *
     * @return BelongsTo<MonitoredSystem, $this>
     */
    public function monitoredSystem(): BelongsTo
    {
        return $this->belongsTo(MonitoredSystem::class);
    }

    /**
     * Get the values associated with the parameter.
     *
     * @return HasMany<ParameterValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ParameterValue::class);
    }

    /**
     * Get the daily metrics associated with the parameter.
     *
     * @return HasMany<ParameterDailyMetric, $this>
     */
    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(ParameterDailyMetric::class);
    }

    /**
     * Get the report items associated with the parameter.
     *
     * @return HasMany<ReportItem, $this>
     */
    public function reportItems(): HasMany
    {
        return $this->hasMany(ReportItem::class);
    }

    /**
     * Get the chart series associated with the parameter.
     *
     * @return HasMany<ChartSeries, $this>
     */
    public function chartSeries(): HasMany
    {
        return $this->hasMany(ChartSeries::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'alert_1_min' => 'double',
            'alert_1_max' => 'double',
            'alert_2_min' => 'double',
            'alert_2_max' => 'double',
            'alert_3_min' => 'double',
            'alert_3_max' => 'double',
            'alert_4_min' => 'double',
            'alert_4_max' => 'double',
        ];
    }
}
