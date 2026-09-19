<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TourMessageRead implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $readerId,
        public string $bookingUuid,
        public string $readUntil,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tour.booking.'.$this->bookingUuid);
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'reader_id' => $this->readerId,
            'read_until' => $this->readUntil,
        ];
    }
}
