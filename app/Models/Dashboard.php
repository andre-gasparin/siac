<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property int $user_id
 * @property int $team_id
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Team $team
 * @property-read Collection<int, DashboardComponent> $components
 */
#[Fillable([
    'title',
    'user_id',
    'team_id',
    'is_public',
])]
class Dashboard extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the user that owns the dashboard.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team that owns the dashboard.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the components for the dashboard.
     *
     * @return HasMany<DashboardComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(DashboardComponent::class);
    }

    /**
     * Scope a query to only include dashboards visible to the user within a team.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForTeamAndUser(Builder $query, int $teamId, int $userId): Builder
    {
        return $query->where('team_id', $teamId)
            ->where(function (Builder $q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('is_public', true);
            });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }
}
