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
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed> $config
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read Collection<int, SpreadsheetImportBatch> $batches
 */
#[Fillable([
    'team_id',
    'name',
    'description',
    'config',
    'is_active',
    'created_by',
    'updated_by',
])]
class SpreadsheetTemplate extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, SoftDeletes;

    /**
     * Get the team that owns the spreadsheet template.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the template.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the template.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the import batches associated with this template.
     *
     * @return HasMany<SpreadsheetImportBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(SpreadsheetImportBatch::class);
    }

    /**
     * Get the email rules associated with this template.
     *
     * @return HasMany<SpreadsheetEmailRule, $this>
     */
    public function emailRules(): HasMany
    {
        return $this->hasMany(SpreadsheetEmailRule::class);
    }

    /**
     * Get the inbox items associated with this template.
     *
     * @return HasMany<SpreadsheetEmailInboxItem, $this>
     */
    public function inboxItems(): HasMany
    {
        return $this->hasMany(SpreadsheetEmailInboxItem::class);
    }

    /**
     * Get the queue items associated with this template.
     *
     * @return HasMany<SpreadsheetImportQueue, $this>
     */
    public function queueItems(): HasMany
    {
        return $this->hasMany(SpreadsheetImportQueue::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
