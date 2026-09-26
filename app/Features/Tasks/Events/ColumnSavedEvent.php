<?php

namespace App\Features\Tasks\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ColumnSavedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $column
     */
    public function __construct(
        public int $boardId,
        public array $column,
        public bool $isNew = false
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
        return 'ColumnSaved';
    }
}
