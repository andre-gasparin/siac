<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $report_id
 * @property int $report_item_id
 * @property int|null $created_by
 * @property int|null $resolved_by
 * @property string $mode
 * @property string $status
 * @property string $original_text
 * @property string $replacement_text
 * @property string|null $reason
 * @property Carbon|null $base_updated_at
 * @property Carbon|null $resolved_at
 */
#[Fillable([
    'team_id',
    'report_id',
    'report_item_id',
    'created_by',
    'resolved_by',
    'mode',
    'status',
    'original_text',
    'replacement_text',
    'reason',
    'base_updated_at',
    'resolved_at',
])]
class ReportSuggestion extends Model
{
    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return BelongsTo<ReportItem, $this> */
    public function reportItem(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    protected function casts(): array
    {
        return [
            'base_updated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
