<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SavedTourResource;
use App\Models\SavedTour;
use App\Models\TourProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SavedTourController extends BaseApiController
{
    /**
     * List current user's saved tours.
     */
    public function index(Request $request): JsonResponse
    {
        $items = SavedTour::where('user_id', $request->user()->id)
            ->with(['tour.provider', 'tour.province', 'tour.images'])
            ->latest()
            ->get();

        return $this->success([
            'data' => SavedTourResource::collection($items),
        ]);
    }

    /**
     * Save a published tour.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tour_id' => 'required|integer|exists:tour_products,id',
        ]);

        $tour = TourProduct::where('id', (int) $request->tour_id)
            ->where('status', 'published')
            ->first();
        if (! $tour) {
            return $this->error('Tour not found or not available.', 404, 'NOT_FOUND');
        }

        $saved = SavedTour::firstOrCreate([
            'user_id' => $request->user()->id,
            'tour_id' => $tour->id,
        ]);

        $saved->load(['tour.provider', 'tour.province', 'tour.images']);

        return $this->success(new SavedTourResource($saved), 201);
    }

    /**
     * Remove a tour from saved list.
     */
    public function destroy(Request $request, int $tourId): Response|JsonResponse
    {
        $deleted = SavedTour::where('user_id', $request->user()->id)
            ->where('tour_id', $tourId)
            ->delete();

        if (! $deleted) {
            return $this->error('Tour was not in your saved list.', 404, 'NOT_FOUND');
        }

        return response()->noContent();
    }
}
