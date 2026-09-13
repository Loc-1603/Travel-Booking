<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourProvinceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'is_featured' => (bool) $this->is_featured,
            'attractions' => $this->whenLoaded('attractions', fn () => $this->attractions->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'description' => $a->description,
                'image' => $a->image,
                'is_famous' => (bool) $a->is_famous,
            ])),
            'tours_count' => $this->when(isset($this->tours_count), (int) $this->tours_count),
        ];
    }
}
