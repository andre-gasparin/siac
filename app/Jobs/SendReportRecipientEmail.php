<?php

namespace App\Jobs;

use App\Features\Reports\Notifications\ReportAccessNotification;
use App\Models\ReportActivity;
use App\Models\ReportRecipient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendReportRecipientEmail implements ShouldBeUnique, ShouldQueue
{
    use FoundationQueueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [1, 5, 10];

    public function __construct(public int $recipientId, public string $batch) {}

    public function uniqueId(): string
    {
        return $this->recipientId.':'.$this->batch;
    }

    public function handle(): void
    {
        $recipient = ReportRecipient::query()->with(['report.team'])->findOrFail($this->recipientId);

        if ($recipient->revoked_at || ! $recipient->report->team->members()->whereKey($recipient->user_id)->exists()) {
            return;
        }

        Notification::route('mail', [$recipient->email => $recipient->name])
            ->notifyNow(new ReportAccessNotification($recipient));

        DB::transaction(function () use ($recipient): void {
            $recipient->refresh()->update([
                'last_sent_at' => now(),
                'last_failed_at' => null,
                'send_count' => $recipient->send_count + 1,
            ]);
            $recipient->report()->increment('email_count');
            $recipient->report()->update(['emailed_at' => now()]);
            ReportActivity::query()->create([
                'team_id' => $recipient->team_id,
                'report_id' => $recipient->report_id,
                'report_recipient_id' => $recipient->id,
                'action' => 'delivery.sent',
                'metadata' => ['batch' => $this->batch, 'email' => $recipient->email],
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $recipient = ReportRecipient::query()->find($this->recipientId);

        if (! $recipient) {
            return;
        }

        $recipient->update(['last_failed_at' => now()]);
        ReportActivity::query()->create([
            'team_id' => $recipient->team_id,
            'report_id' => $recipient->report_id,
            'report_recipient_id' => $recipient->id,
            'action' => 'delivery.failed',
            'metadata' => ['batch' => $this->batch, 'error' => $exception?->getMessage()],
        ]);
    }
}
