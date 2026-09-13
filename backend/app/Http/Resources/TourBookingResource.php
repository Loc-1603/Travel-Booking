<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'pricing_mode' => $this->pricing_mode,
            'duration_value' => (int) $this->duration_value,
            'base_fixed' => (float) $this->base_fixed,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
            'transport_fee' => (float) ($this->transport_fee ?? 0),
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'tax_amount' => (float) ($this->tax_amount ?? 0),
            'total_price' => (float) $this->total_price,
            'currency' => $this->currency,
            'meeting_point' => $this->meeting_point,
            'customer_notes' => $this->customer_notes,
            'tour' => new TourProductResource($this->whenLoaded('tour')),
            'slot' => new TourSlotResource($this->whenLoaded('slot')),
            'can_open_dispute' => $this->when($this->resource->exists, fn () => $this->resource->canOpenDispute()),
        ];
    }
}
