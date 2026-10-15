<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedGuideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'provider_uuid' => $this->whenLoaded('provider', fn () => $this->provider?->uuid),
            'guide' => $this->whenLoaded('provider', fn () => $this->provider ? new TourProviderResource($this->provider) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
