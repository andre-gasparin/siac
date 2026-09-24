<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property int $spreadsheet_template_id
 * @property string $source_type
 * @property int|null $spreadsheet_email_inbox_item_id
 * @property string $file_name
 * @property string $file_path
 * @property Carbon|null $reference_date
 * @property string $status
 * @property int $attempts
 * @property string|null $error_message
 * @property string|null $error_trace
 * @property int $saved_values_count
 * @property int|null $spreadsheet_import_batch_id
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read SpreadsheetTemplate $template
 * @property-read SpreadsheetEmailInboxItem|null $inboxItem
 * @property-read SpreadsheetImportBatch|null $batch
 */
#[Fillable([
    'team_id',
    'user_id',
    'spreadsheet_template_id',
    'source_type',
    'spreadsheet_email_inbox_item_id',
    'file_name',
    'file_path',
    'reference_date',
    'status',
    'attempts',
    'error_message',
    'error_trace',
    'saved_values_count',
    'spreadsheet_import_batch_id',
    'started_at',
    'completed_at',
])]
class SpreadsheetImportQueue extends Model
{
    use HasFactory;

    /**
     * Get the team that owns the queue entry.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who enqueued this item (null if automated/system).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the spreadsheet template associated with this queue item.
     *
     * @return BelongsTo<SpreadsheetTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetTemplate::class, 'spreadsheet_template_id');
    }

    /**
     * Get the email inbox item associated with this queue item if from email.
     *
     * @return BelongsTo<SpreadsheetEmailInboxItem, $this>
     */
    public function inboxItem(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetEmailInboxItem::class, 'spreadsheet_email_inbox_item_id');
    }

    /**
     * Get the resulting import batch after successful processing.
     *
     * @return BelongsTo<SpreadsheetImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetImportBatch::class, 'spreadsheet_import_batch_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
            'attempts' => 'integer',
            'saved_values_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
