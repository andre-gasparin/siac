<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $spreadsheet_email_rule_id
 * @property int $spreadsheet_template_id
 * @property string|null $email_message_id
 * @property string $sender_email
 * @property string|null $sender_name
 * @property string $subject
 * @property string|null $body_snippet
 * @property Carbon|null $email_received_at
 * @property string $file_name
 * @property string $file_path
 * @property int $file_size_bytes
 * @property Carbon|null $extracted_reference_date
 * @property string $status
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read SpreadsheetEmailRule|null $rule
 * @property-read SpreadsheetTemplate $template
 * @property-read User|null $confirmedBy
 * @property-read Collection<int, SpreadsheetImportQueue> $queueItems
 */
#[Fillable([
    'team_id',
    'spreadsheet_email_rule_id',
    'spreadsheet_template_id',
    'email_message_id',
    'sender_email',
    'sender_name',
    'subject',
    'body_snippet',
    'email_received_at',
    'file_name',
    'file_path',
    'file_size_bytes',
    'extracted_reference_date',
    'status',
    'confirmed_by',
    'confirmed_at',
])]
class SpreadsheetEmailInboxItem extends Model
{
    use HasFactory;

    /**
     * Get the team that owns this inbox item.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the matching email rule if any.
     *
     * @return BelongsTo<SpreadsheetEmailRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetEmailRule::class, 'spreadsheet_email_rule_id');
    }

    /**
     * Get the template assigned to this inbox item.
     *
     * @return BelongsTo<SpreadsheetTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetTemplate::class, 'spreadsheet_template_id');
    }

    /**
     * Get the user who confirmed this inbox item.
     *
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Get queue items created from this inbox item.
     *
     * @return HasMany<SpreadsheetImportQueue, $this>
     */
    public function queueItems(): HasMany
    {
        return $this->hasMany(SpreadsheetImportQueue::class, 'spreadsheet_email_inbox_item_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_received_at' => 'datetime',
            'extracted_reference_date' => 'date',
            'file_size_bytes' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }
}
