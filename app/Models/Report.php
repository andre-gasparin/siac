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
 * @property int|null $created_by
 * @property int|null $finished_by
 * @property string $title
 * @property Carbon|null $date_reference
 * @property string $status
 * @property string|null $comment
 * @property Carbon|null $finished_at
 * @property Carbon|null $emailed_at
 * @property int $email_count
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $creator
 * @property-read User|null $finisher
 * @property-read Collection<int, ReportItem> $items
 */
#[Fillable([
    'team_id',
    'created_by',
    'finished_by',
    'title',
    'date_reference',
    'status',
    'comment',
    'finished_at',
    'emailed_at',
    'email_count',
    'settings',
])]
class Report extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, SoftDeletes;

    /**
     * Get the team that owns the report.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who completed the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by');
    }

    /**
     * Get the items belonging to this report.
     *
     * @return HasMany<ReportItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReportItem::class);
    }

    /** @return HasMany<ReportRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(ReportRecipient::class);
    }

    /** @return HasMany<ReportComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ReportComment::class);
    }

    /** @return HasMany<ReportActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ReportActivity::class);
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
            'date_reference' => 'date',
            'finished_at' => 'datetime',
            'emailed_at' => 'datetime',
            'email_count' => 'integer',
            'settings' => 'array',
        ];
    }
}
