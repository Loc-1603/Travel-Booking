<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TourMessageInboxUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $vendorId,
        public string $bookingUuid,
        public array $lastMessage,
        public int $unreadCount,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('vendor.'.$this->vendorId.'.tour-messages');
    }

    public function broadcastAs(): string
    {
        return 'inbox.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'booking_uuid' => $this->bookingUuid,
            'unread_count' => $this->unreadCount,
            'last_message' => $this->lastMessage,
        ];
    }
}
