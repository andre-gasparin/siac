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
 * @property int|null $user_id
 * @property int|null $report_recipient_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property-read User|null $user
 * @property-read ReportRecipient|null $recipient
 */
#[Fillable(['team_id', 'report_id', 'report_item_id', 'user_id', 'report_recipient_id', 'body'])]
class ReportComment extends Model
{
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ReportRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(ReportRecipient::class, 'report_recipient_id');
    }
}
