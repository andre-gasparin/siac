<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property int|null $spreadsheet_template_id
 * @property string $file_name
 * @property string|null $file_path
 * @property Carbon|null $reference_date
 * @property string $status
 * @property int $saved_values_count
 * @property array<string, mixed>|null $snapshot
 * @property Carbon|null $reverted_at
 * @property int|null $reverted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read User|null $revertedBy
 * @property-read SpreadsheetTemplate|null $template
 */
#[Fillable([
    'team_id',
    'user_id',
    'spreadsheet_template_id',
    'file_name',
    'file_path',
    'reference_date',
    'status',
    'saved_values_count',
    'snapshot',
    'reverted_at',
    'reverted_by',
])]
class SpreadsheetImportBatch extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Get the team that owns the batch.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who imported the batch.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who reverted the batch.
     *
     * @return BelongsTo<User, $this>
     */
    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    /**
     * Get the spreadsheet template associated with this batch.
     *
     * @return BelongsTo<SpreadsheetTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetTemplate::class, 'spreadsheet_template_id');
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
            'saved_values_count' => 'integer',
            'snapshot' => 'array',
            'reverted_at' => 'datetime',
        ];
    }
}
