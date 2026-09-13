<?php

namespace App\Events;

use App\Http\Resources\TourMessageResource;
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

        return [
            'message' => (new TourMessageResource($this->message))->toArray(request()),
        ];
    }
}
