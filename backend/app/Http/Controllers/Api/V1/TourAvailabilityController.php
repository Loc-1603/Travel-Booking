<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TourSlotResource;
use App\Services\TourAvailabilityService;
use App\Services\TourQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourAvailabilityController extends BaseApiController
{
    public function __construct(
        protected TourQueryService $tours,
        protected TourAvailabilityService $availability
    ) {}

    /**
     * Upcoming slots for a tour (public). Query: from, to (Y-m-d, default next 30 days).
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date|after_or_equal:today',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $tour = $this->tours->baseQuery()->where('tour_products.uuid', $uuid)->first();
        if (! $tour) {
            return $this->error('Tour not found.', 404, 'NOT_FOUND');
        }

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->addDays(30)->toDateString());

        $slots = $this->availability->listSlots($tour->id, $from, $to);

        return $this->success(TourSlotResource::collection($slots));
    }
}
