<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'base_fixed' => (float) $this->base_fixed,
            'base_price_hourly' => (float) $this->base_price_hourly,
            'base_price_daily' => (float) $this->base_price_daily,
            'transport_fee' => $this->transport_fee !== null ? (float) $this->transport_fee : null,
            'transport_desc' => $this->transport_desc,
            'meeting_point' => $this->meeting_point,
            'max_group_size' => (int) $this->max_group_size,
            'duration_unit' => $this->duration_unit,
            'status' => $this->status,
            'provider' => $this->whenLoaded('provider', fn () => [
                'id' => $this->provider->id,
                'uuid' => $this->provider->uuid,
                'business_name' => $this->provider->business_name,
                'bio' => $this->provider->bio,
                'avatar' => $this->provider->resolvedAvatarUrl(),
                'languages' => $this->provider->languages,
            ]),
            'province' => $this->whenLoaded('province', fn () => [
                'id' => $this->province->id,
                'name' => $this->province->name,
                'slug' => $this->province->slug,
                'image' => $this->province->image,
                // Eager-loaded by TourQueryService::detail(); [] on list endpoints.
                'attractions' => $this->province->relationLoaded('attractions')
                    ? $this->province->attractions->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'description' => $a->description,
                        'image' => $a->image,
                        'is_famous' => (bool) $a->is_famous,
                    ])->values()
                    : [],
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'alt_text' => $img->alt_text,
                'is_banner' => (bool) $img->is_banner,
                'sort_order' => $img->sort_order,
            ])),
            'banner_image' => $this->whenLoaded('images', function () {
                $banner = $this->images->firstWhere('is_banner', true) ?? $this->images->first();
                return $banner ? ['id' => $banner->id, 'url' => $banner->url, 'alt_text' => $banner->alt_text] : null;
            }),
            'slots' => TourSlotResource::collection($this->whenLoaded('slots')),
            'average_rating' => $this->when(isset($this->average_rating), fn () => round((float) $this->average_rating, 2)),
            'review_count' => $this->when(isset($this->review_count), fn () => (int) $this->review_count),
        ];
    }
}
