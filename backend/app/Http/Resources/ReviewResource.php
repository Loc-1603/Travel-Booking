<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource chung cho review hotel (booking_id) và tour/guide (tour_booking_id).
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hotelBookingLoaded = $this->relationLoaded('booking') && $this->booking !== null;
        $tourBookingLoaded = $this->relationLoaded('tourBooking') && $this->tourBooking !== null;

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'tour_booking_id' => $this->tour_booking_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
            // Ảnh minh hoạ của review (chỉ khi đã eager-load).
            'images' => $this->when(
                $this->relationLoaded('images'),
                fn () => $this->images->map(fn ($img) => ['id' => $img->id, 'url' => $img->url])->values()
            ),
            // Ai đã đánh giá (hotel hoặc tour booking).
            'customer_name' => $this->when(
                ($hotelBookingLoaded && $this->booking->relationLoaded('customer'))
                    || ($tourBookingLoaded && $this->tourBooking->relationLoaded('customer')),
                fn () => $this->booking?->customer?->name ?? $this->tourBooking?->customer?->name
            ),
            // Tour context (chỉ khi review tour được eager-load kèm tour).
            'tour' => $this->when(
                $tourBookingLoaded && $this->tourBooking->relationLoaded('tour') && $this->tourBooking->tour,
                fn () => [
                    'id' => $this->tourBooking->tour->id,
                    'uuid' => $this->tourBooking->tour->uuid,
                    'title' => $this->tourBooking->tour->title,
                ]
            ),
        ];
    }
}
