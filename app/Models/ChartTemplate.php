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
 * @property int|null $user_id
 * @property string $name
 * @property bool $is_favorite
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $markers
 * @property float|null $y1_min
 * @property float|null $y1_max
 * @property float|null $y2_min
 * @property float|null $y2_max
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read Collection<int, ChartSeries> $series
 */
#[Fillable([
    'team_id',
    'user_id',
    'name',
    'is_favorite',
    'options',
    'markers',
    'y1_min',
    'y1_max',
    'y2_min',
    'y2_max',
])]
class ChartTemplate extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the team that owns this chart template.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created this chart template.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the chart series associated with this template.
     *
     * @return HasMany<ChartSeries, $this>
     */
    public function series(): HasMany
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
            'is_favorite' => 'boolean',
            'options' => 'array',
            'markers' => 'array',
            'y1_min' => 'double',
            'y1_max' => 'double',
            'y2_min' => 'double',
            'y2_max' => 'double',
        ];
    }
}
