<?php

namespace App\Events;

use App\Models\TourMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TourMessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public TourMessage $message,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tour.booking.'.$this->message->booking->uuid);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');

        // Neutral payload: every client computes `is_mine` locally by
        // comparing `sender_id` with its own user id. Never use request()
        // here — it reflects the SENDER, so receivers would see a flipped side.
        return [
            'message' => [
                'id' => $this->message->id,
                'tour_booking_id' => $this->message->tour_booking_id,
                'sender_id' => $this->message->sender_id,
                'sender_name' => $this->message->sender?->name,
                'body' => $this->message->body,
                'read_at' => $this->message->read_at?->toIso8601String(),
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
        ];
    }
}
