<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tour_booking_id' => $this->tour_booking_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'approved' => $this->approved,
            'created_at' => $this->created_at?->toIso8601String(),
            // Ảnh minh hoạ của review (chỉ khi đã eager-load).
            'images' => $this->when(
                $this->relationLoaded('images'),
                fn () => $this->images->map(fn ($img) => ['id' => $img->id, 'url' => $img->url])->values()
            ),
            // Guide profile context: who booked and which tour (only when booking eager-loaded).
            'customer_name' => $this->when(
                $this->relationLoaded('booking') && $this->booking?->relationLoaded('customer'),
                fn () => $this->booking->customer?->name
            ),
            'tour' => $this->when(
                $this->relationLoaded('booking') && $this->booking?->relationLoaded('tour') && $this->booking->tour,
                fn () => [
                    'id' => $this->booking->tour->id,
                    'uuid' => $this->booking->tour->uuid,
                    'title' => $this->booking->tour->title,
                ]
            ),
        ];
    }
}
