<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\TourSearchRequest;
use App\Http\Resources\TourProductResource;
use App\Models\TourAttraction;
use App\Models\TourProvince;
use App\Services\TourQueryService;
use Illuminate\Http\JsonResponse;

class TourSearchController extends BaseApiController
{
    public function __construct(
        protected TourQueryService $tours
    ) {}

    /**
     * Search published 1vs1 tours: province, date availability, price, rating.
     */
    public function index(TourSearchRequest $request): JsonResponse
    {
        $query = $this->tours->baseQuery();

        if ($request->filled('province_id')) {
            $query->where('province_id', $request->province_id);
        } elseif ($request->filled('province_slug')) {
            $province = TourProvince::where('slug', $request->province_slug)->first();
            if (! $province) {
                return $this->success(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => (int) $request->input('per_page', 15), 'total' => 0]]);
            }
            $query->where('province_id', $province->id);
        }

        if ($request->filled('attraction_id')) {
            $attraction = TourAttraction::find($request->attraction_id);
            if ($attraction) {
                $query->where('province_id', $attraction->province_id);
            }
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->input('date_from', $request->input('date_to'));
            $to = $request->input('date_to', $request->input('date_from'));
            $tourIds = $this->tours->tourIdsWithAvailability($from, $to);
            if ($tourIds === []) {
                return $this->success(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => (int) $request->input('per_page', 15), 'total' => 0]]);
            }
            $query->whereIn('tour_products.id', $tourIds);
        }

        if ($request->filled('min_price')) {
            $query->where('base_price_hourly', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('base_price_hourly', '<=', $request->max_price);
        }

        $query = $this->tours->withRatingStats($query);

        if ($request->filled('min_rating')) {
            $query->having('average_rating', '>=', (float) $request->min_rating);
        }

        $this->tours->applySort($query, $request->input('sort'));

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->with(['provider', 'province', 'images'])->paginate($perPage);

        return $this->success([
            'data' => TourProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Single published tour with provider, province + upcoming slots.
     */
    public function show(string $uuid): JsonResponse
    {
        $tour = $this->tours->detail($uuid);
        if (! $tour) {
            return $this->error('Tour not found.', 404, 'NOT_FOUND');
        }

        return $this->success(new TourProductResource($tour));
    }
}
