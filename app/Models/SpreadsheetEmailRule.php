<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $spreadsheet_template_id
 * @property string $name
 * @property bool $is_active
 * @property int $priority
 * @property string|null $subject_operator
 * @property string|null $subject_value
 * @property string|null $body_operator
 * @property string|null $body_value
 * @property string|null $sender_operator
 * @property string|null $sender_value
 * @property string|null $attachment_name_operator
 * @property string|null $attachment_name_value
 * @property string|null $date_extraction_source
 * @property string|null $date_extraction_pattern
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read SpreadsheetTemplate $template
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read Collection<int, SpreadsheetEmailInboxItem> $inboxItems
 */
#[Fillable([
    'team_id',
    'spreadsheet_template_id',
    'name',
    'is_active',
    'priority',
    'subject_operator',
    'subject_value',
    'body_operator',
    'body_value',
    'sender_operator',
    'sender_value',
    'attachment_name_operator',
    'attachment_name_value',
    'date_extraction_source',
    'date_extraction_pattern',
    'created_by',
    'updated_by',
])]
class SpreadsheetEmailRule extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the team that owns the rule.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the spreadsheet template associated with this rule.
     *
     * @return BelongsTo<SpreadsheetTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetTemplate::class, 'spreadsheet_template_id');
    }

    /**
     * Get the user who created the rule.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the rule.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get inbox items captured using this rule.
     *
     * @return HasMany<SpreadsheetEmailInboxItem, $this>
     */
    public function inboxItems(): HasMany
    {
        return $this->hasMany(SpreadsheetEmailInboxItem::class, 'spreadsheet_email_rule_id');
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
            'priority' => 'integer',
        ];
    }
}
