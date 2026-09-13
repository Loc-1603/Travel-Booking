<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tour_id' => $this->tour_id,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => is_string($this->start_time) ? substr($this->start_time, 0, 5) : $this->start_time?->format('H:i'),
            'end_time' => is_string($this->end_time) ? substr($this->end_time, 0, 5) : $this->end_time?->format('H:i'),
            'status' => $this->status,
            'price_override' => $this->price_override !== null ? (float) $this->price_override : null,
        ];
    }
}
