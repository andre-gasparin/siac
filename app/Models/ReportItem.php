<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $report_id
 * @property int|null $monitored_system_id
 * @property int|null $parameter_id
 * @property int|null $parent_report_item_id
 * @property Carbon|null $date_reference
 * @property int $sort_order
 * @property bool $show_data_results
 * @property bool $hide_data
 * @property bool $is_stopped
 * @property string|null $comment
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Report $report
 * @property-read MonitoredSystem|null $monitoredSystem
 * @property-read Parameter|null $parameter
 * @property-read ReportItem|null $parentReportItem
 * @property-read Collection<int, ReportItem> $childReportItems
 */
#[Fillable([
    'team_id',
    'report_id',
    'monitored_system_id',
    'parameter_id',
    'parent_report_item_id',
    'date_reference',
    'sort_order',
    'show_data_results',
    'hide_data',
    'is_stopped',
    'comment',
    'metadata',
])]
class ReportItem extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the team that owns this report item.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the report this item belongs to.
     *
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get the monitored system associated with this item.
     *
     * @return BelongsTo<MonitoredSystem, $this>
     */
    public function monitoredSystem(): BelongsTo
    {
        return $this->belongsTo(MonitoredSystem::class);
    }

    /**
     * Get the parameter associated with this item.
     *
     * @return BelongsTo<Parameter, $this>
     */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }

    /**
     * Get the parent report item.
     *
     * @return BelongsTo<ReportItem, $this>
     */
    public function parentReportItem(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class, 'parent_report_item_id');
    }

    /**
     * Get the child report items.
     *
     * @return HasMany<ReportItem, $this>
     */
    public function childReportItems(): HasMany
    {
        return $this->hasMany(ReportItem::class, 'parent_report_item_id');
    }

    /** @return HasMany<ReportComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ReportComment::class);
    }

    /** @return HasMany<ReportItemRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ReportItemRevision::class);
    }

    /** @return HasMany<ReportSuggestion, $this> */
    public function suggestions(): HasMany
    {
        return $this->hasMany(ReportSuggestion::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_reference' => 'datetime',
            'sort_order' => 'integer',
            'show_data_results' => 'boolean',
            'hide_data' => 'boolean',
            'is_stopped' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
