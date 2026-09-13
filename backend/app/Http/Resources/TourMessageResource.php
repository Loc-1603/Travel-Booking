<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tour_booking_id' => $this->tour_booking_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->whenLoaded('sender', fn () => $this->sender?->name),
            'is_mine' => $request->user() ? (int) $this->sender_id === (int) $request->user()->id : false,
            'body' => $this->body,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
