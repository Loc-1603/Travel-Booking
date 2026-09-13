<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TourProvinceResource;
use App\Models\TourProvince;
use Illuminate\Http\JsonResponse;

class TourProvinceController extends BaseApiController
{
    /**
     * List tour provinces (public). Featured first.
     */
    public function index(): JsonResponse
    {
        $provinces = TourProvince::with(['attractions' => fn ($q) => $q->where('is_famous', true)])
            ->withCount(['tours' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(TourProvinceResource::collection($provinces));
    }

    /**
     * Province detail by slug with famous attractions + published tours.
     */
    public function show(string $slug): JsonResponse
    {
        $province = TourProvince::where('slug', $slug)
            ->with(['attractions' => fn ($q) => $q->orderByDesc('is_famous')->orderBy('name')])
            ->withCount(['tours' => fn ($q) => $q->where('status', 'published')])
            ->firstOrFail();

        return $this->success(new TourProvinceResource($province));
    }
}
