<?php

namespace App\Models;

use App\Features\Teams\Concerns\GeneratesUniqueTeamSlugs;
use App\Features\Teams\Enums\TeamRole;
use App\Features\Teams\Policies\TeamPolicy;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property bool $is_personal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, MonitoredSystem> $monitoredSystems
 * @property-read Collection<int, Parameter> $parameters
 * @property-read Collection<int, ParameterValue> $parameterValues
 * @property-read Collection<int, ParameterDailyMetric> $parameterDailyMetrics
 * @property-read Collection<int, Report> $reports
 * @property-read Collection<int, ReportItem> $reportItems
 * @property-read Collection<int, ChartTemplate> $chartTemplates
 * @property-read Collection<int, ChartSeries> $chartSeries
 */
#[Fillable(['name', 'slug', 'is_active', 'is_personal'])]
#[UsePolicy(TeamPolicy::class)]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get all monitored systems for this team.
     *
     * @return HasMany<MonitoredSystem, $this>
     */
    public function monitoredSystems(): HasMany
    {
        return $this->hasMany(MonitoredSystem::class);
    }

    /**
     * Get all parameters for this team.
     *
     * @return HasMany<Parameter, $this>
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class);
    }

    /**
     * Get all parameter values for this team.
     *
     * @return HasMany<ParameterValue, $this>
     */
    public function parameterValues(): HasMany
    {
        return $this->hasMany(ParameterValue::class);
    }

    /**
     * Get all parameter daily metrics for this team.
     *
     * @return HasMany<ParameterDailyMetric, $this>
     */
    public function parameterDailyMetrics(): HasMany
    {
        return $this->hasMany(ParameterDailyMetric::class);
    }

    /**
     * Get all reports for this team.
     *
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get all report items for this team.
     *
     * @return HasMany<ReportItem, $this>
     */
    public function reportItems(): HasMany
    {
        return $this->hasMany(ReportItem::class);
    }

    /**
     * Get all chart templates for this team.
     *
     * @return HasMany<ChartTemplate, $this>
     */
    public function chartTemplates(): HasMany
    {
        return $this->hasMany(ChartTemplate::class);
    }

    /**
     * Get all chart series for this team.
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
            'is_active' => 'boolean',
            'is_personal' => 'boolean',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
