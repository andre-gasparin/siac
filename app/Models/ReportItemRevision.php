<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'report_item_id',
    'user_id',
    'source',
    'document',
    'html',
    'show_data_results',
    'hide_data',
    'is_stopped',
])]
class ReportItemRevision extends Model
{
    protected $attributes = [
        'source' => 'manual',
        'show_data_results' => false,
        'hide_data' => false,
        'is_stopped' => false,
    ];

    /** @return BelongsTo<ReportItem, $this> */
    public function reportItem(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'show_data_results' => 'boolean',
            'hide_data' => 'boolean',
            'is_stopped' => 'boolean',
        ];
    }
}
