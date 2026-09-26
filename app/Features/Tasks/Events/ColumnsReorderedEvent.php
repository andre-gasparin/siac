<?php

namespace App\Features\Tasks\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ColumnsReorderedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $orderedColumnIds
     */
    public function __construct(
        public int $boardId,
        public array $orderedColumnIds
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('kanban.board.'.$this->boardId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ColumnsReordered';
    }
}
