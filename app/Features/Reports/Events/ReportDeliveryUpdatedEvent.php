<?php

namespace App\Features\Reports\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ReportDeliveryUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $reportId,
        public ?int $recipientId,
        public string $status,
        public ?string $recipientName = null,
        public ?string $recipientEmail = null,
        public ?string $deliveryType = null,
        public ?string $error = null,
    ) {}

    public static function dispatchSafely(
        int $reportId,
        ?int $recipientId,
        string $status,
        ?string $recipientName = null,
        ?string $recipientEmail = null,
        ?string $deliveryType = null,
        ?string $error = null,
    ): void {
        try {
            broadcast(new self(
                reportId: $reportId,
                recipientId: $recipientId,
                status: $status,
                recipientName: $recipientName,
                recipientEmail: $recipientEmail,
                deliveryType: $deliveryType,
                error: $error,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('reports.'.$this->reportId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ReportDeliveryUpdated';
    }
}
