<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\TourProviderSearchRequest;
use App\Http\Resources\TourProviderResource;
use App\Models\TourProvince;
use App\Services\TourQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourProviderController extends BaseApiController
{
    public function __construct(
        protected TourQueryService $tours
    ) {}

    /**
     * List approved 1vs1 vendors (guides), ranked by score.
     *
     * score = AVG(rating 1..5) * COUNT(approved, visible reviews).
     * Optional multi-day filter: only vendors free for EVERY day in [from, to].
     */
    public function index(TourProviderSearchRequest $request): JsonResponse
    {
        $provinceId = $this->resolveProvinceId($request);
        if ($provinceId === false) {
            return $this->success($this->emptyPage((int) $request->input('per_page', 15)));
        }

        $query = $this->tours->withProviderRatingStats(
            $this->tours->providerBaseQuery(),
            $provinceId
        );

        if ($provinceId !== null) {
            $query->whereIn('tour_providers.id', $this->tours->providerIdsInProvince($provinceId));
        }

        if ($request->filled('from') || $request->filled('to')) {
            if ($provinceId === null) {
                return $this->error('province_slug or province_id is required when filtering by date.', 422, 'PROVINCE_REQUIRED');
            }
            $from = $request->input('from', $request->input('to'));
            $to = $request->input('to', $request->input('from'));
            $freeIds = $this->tours->providerIdsFreeForAllDays($provinceId, $from, $to);
            if ($freeIds === []) {
                return $this->success($this->emptyPage((int) $request->input('per_page', 15)));
            }
            $query->whereIn('tour_providers.id', $freeIds);
        }

        if ($request->filled('min_rating')) {
            $query->having('average_rating', '>=', (float) $request->input('min_rating'));
        }

        $this->tours->applyProviderSort($query, $request->input('sort', 'score'));

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->with(['vendor', 'tours' => function ($q) use ($provinceId): void {
            $q->where('status', 'published')
                ->when($provinceId !== null, fn ($qq) => $qq->where('province_id', $provinceId))
                ->with('province:id,name,slug,image')
                ->orderBy('title');
        }])->paginate($perPage);

        return $this->success([
            'data' => TourProviderResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Vendor profile for GuideDetail: bio, tours in province, rating stats.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['province_slug' => 'nullable|string|max:150']);

        $provinceId = null;
        if ($request->filled('province_slug')) {
            $province = TourProvince::where('slug', $request->input('province_slug'))->first();
            if (! $province) {
                return $this->error('Province not found.', 404, 'NOT_FOUND');
            }
            $provinceId = $province->id;
        }

        $provider = $this->tours->providerDetail($uuid, $provinceId);
        if (! $provider) {
            return $this->error('Guide not found.', 404, 'NOT_FOUND');
        }

        return $this->success(new TourProviderResource($provider));
    }

    /**
     * @return int|false|null province id, false when slug given but missing, null when no filter
     */
    private function resolveProvinceId(TourProviderSearchRequest $request): int|false|null
    {
        if ($request->filled('province_id')) {
            return (int) $request->input('province_id');
        }

        if ($request->filled('province_slug')) {
            $province = TourProvince::where('slug', $request->input('province_slug'))->first();

            return $province ? $province->id : false;
        }

        return null;
    }

    private function emptyPage(int $perPage): array
    {
        return [
            'data' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
        ];
    }
}
