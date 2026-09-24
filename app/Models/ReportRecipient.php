<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $report_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string $access_token
 * @property string $token_hash
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_accessed_at
 * @property Carbon|null $last_sent_at
 * @property Carbon|null $last_failed_at
 * @property int $send_count
 * @property-read Team $team
 * @property-read Report $report
 * @property-read User|null $user
 */
#[Fillable([
    'team_id',
    'report_id',
    'user_id',
    'name',
    'email',
    'access_token',
    'token_hash',
    'revoked_at',
    'last_accessed_at',
    'last_sent_at',
    'last_failed_at',
    'send_count',
])]
#[Hidden(['access_token', 'token_hash'])]
class ReportRecipient extends Model
{
    protected $attributes = [
        'send_count' => 0,
    ];

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ReportComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ReportComment::class);
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'revoked_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'send_count' => 'integer',
        ];
    }
}
