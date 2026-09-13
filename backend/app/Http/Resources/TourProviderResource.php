<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tours = $this->whenLoaded('tours', function () {
            return $this->tours->map(fn ($tour) => [
                'id' => $tour->id,
                'uuid' => $tour->uuid,
                'title' => $tour->title,
                'description' => $tour->description,
                'base_fixed' => (float) $tour->base_fixed,
                'base_price_hourly' => (float) $tour->base_price_hourly,
                'base_price_daily' => (float) $tour->base_price_daily,
                'transport_fee' => $tour->transport_fee !== null ? (float) $tour->transport_fee : null,
                'meeting_point' => $tour->meeting_point,
                'status' => $tour->status,
                'province' => $tour->relationLoaded('province') && $tour->province ? [
                    'id' => $tour->province->id,
                    'name' => $tour->province->name,
                    'slug' => $tour->province->slug,
                    'image' => $tour->province->image,
                ] : null,
            ]);
        });

        // Primary tour = cheapest published offer, used as booking entry point.
        $primaryTour = null;
        if ($this->relationLoaded('tours') && $this->tours->isNotEmpty()) {
            $primary = $this->tours
                ->sortBy(fn ($t) => ((float) $t->base_fixed) + ((float) $t->base_price_hourly))
                ->first();
            $primaryTour = [
                'id' => $primary->id,
                'uuid' => $primary->uuid,
                'title' => $primary->title,
            ];
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'business_name' => $this->business_name,
            'bio' => $this->bio,
            // Own upload first, then the vendor account avatar (may be null → frontend shows initial fallback).
            'avatar' => $this->resource->resolvedAvatarUrl(),
            'languages' => $this->languages ?? [],
            'status' => $this->when(isset($this->status), $this->status),
            'average_rating' => $this->when(isset($this->average_rating), fn () => round((float) $this->average_rating, 2)),
            'review_count' => $this->when(isset($this->review_count), fn () => (int) $this->review_count),
            'score' => $this->when(isset($this->score), fn () => round((float) $this->score, 2)),
            'tours_count' => $this->when(isset($this->tours_count), fn () => (int) $this->tours_count),
            'price_from' => $this->when(isset($this->price_from), fn () => $this->price_from !== null ? (float) $this->price_from : null),
            'primary_tour' => $primaryTour,
            'tours' => $tours,
        ];
    }
}
