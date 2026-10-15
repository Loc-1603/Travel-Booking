<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SavedGuideResource;
use App\Models\SavedGuide;
use App\Models\TourProvider;
use App\Services\TourQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SavedGuideController extends BaseApiController
{
    public function __construct(
        protected TourQueryService $tours
    ) {}

    /**
     * List current user's saved guides.
     */
    public function index(Request $request): JsonResponse
    {
        $items = SavedGuide::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $providers = $this->guidesById($items->pluck('provider_id')->unique()->all());
        $items->each(fn (SavedGuide $item) => $item->setRelation('provider', $providers->get($item->provider_id)));

        return $this->success([
            'data' => SavedGuideResource::collection($items),
        ]);
    }

    /**
     * Save an approved guide.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'provider_uuid' => 'required|string|max:36',
        ]);

        $provider = $this->tours->providerBaseQuery()
            ->where('uuid', $request->input('provider_uuid'))
            ->first();
        if (! $provider) {
            return $this->error('Guide not found or not available.', 404, 'NOT_FOUND');
        }

        $saved = SavedGuide::firstOrCreate([
            'user_id' => $request->user()->id,
            'provider_id' => $provider->id,
        ]);

        $saved->setRelation('provider', $this->guidesById([$provider->id])->get($provider->id));

        return $this->success(new SavedGuideResource($saved), 201);
    }

    /**
     * Remove a guide from saved list.
     */
    public function destroy(Request $request, string $providerUuid): Response|JsonResponse
    {
        $providerId = TourProvider::withTrashed()->where('uuid', $providerUuid)->value('id');

        $deleted = $providerId
            ? SavedGuide::where('user_id', $request->user()->id)
                ->where('provider_id', $providerId)
                ->delete()
            : 0;

        if (! $deleted) {
            return $this->error('Guide was not in your saved list.', 404, 'NOT_FOUND');
        }

        return response()->noContent();
    }

    /**
     * Hydrate approved providers with rating stats and public relations.
     *
     * @param  array<int>  $ids
     * @return Collection<int, TourProvider>
     */
    private function guidesById(array $ids): Collection
    {
        return $this->tours
            ->withProviderRatingStats($this->tours->providerBaseQuery())
            ->whereIn('tour_providers.id', $ids)
            ->with([
                'vendor',
                'tours' => function ($q): void {
                    $q->where('status', 'published')
                        ->with('province:id,name,slug,image')
                        ->orderBy('title');
                },
            ])
            ->get()
            ->keyBy('id');
    }
}
