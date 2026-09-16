<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TourBookingStatus;
use App\Http\Requests\Api\SubmitTourReviewRequest;
use App\Http\Resources\TourReviewResource;
use App\Models\TourBooking;
use App\Models\TourReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourReviewController extends BaseApiController
{
    /**
     * Submit review: only owner of a confirmed/ongoing/completed tour booking; one review per booking.
     */
    public function store(SubmitTourReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $booking = TourBooking::where('id', $request->tour_booking_id)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $this->authorize('view', $booking);

        $allowed = [
            TourBookingStatus::CONFIRMED->value,
            TourBookingStatus::ONGOING->value,
            TourBookingStatus::COMPLETED->value,
        ];

        if (! in_array($booking->status, $allowed, true)) {
            return $this->error('Review only allowed for confirmed, ongoing or completed bookings.', 422, 'INVALID_BOOKING_STATE');
        }

        if ($booking->review()->exists()) {
            return $this->error('One review per booking.', 422, 'REVIEW_EXISTS');
        }

        $review = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $booking) {
            $created = TourReview::create([
                'tour_booking_id' => $booking->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'approved' => false,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $i => $file) {
                    $path = $file->store('review-images', 'public');
                    $created->images()->create(['path' => $path, 'sort_order' => $i]);
                }
            }

            return $created;
        });

        return $this->success(new TourReviewResource($review->load('images')), 201);
    }

    /**
     * List reviews: filter by tour uuid, provider (guide) uuid or booking id.
     * Only approved + visible. Paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'tour_uuid' => 'nullable|string',
            'provider_uuid' => 'nullable|string',
            'tour_booking_id' => 'nullable|exists:tour_bookings,id',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = TourReview::query()
            ->where('approved', true)
            ->where('hidden', false)
            ->whereHas('booking');

        if ($request->filled('tour_uuid')) {
            $query->whereHas('booking', fn ($q) => $q->whereHas(
                'tour',
                fn ($t) => $t->where('uuid', $request->tour_uuid)
            ));
        }
        if ($request->filled('provider_uuid')) {
            $query->whereHas('booking', fn ($q) => $q->whereHas(
                'provider',
                fn ($p) => $p->where('uuid', $request->provider_uuid)
            ));
        }
        if ($request->filled('tour_booking_id')) {
            $query->where('tour_booking_id', $request->tour_booking_id);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->with(['images', 'booking.customer:id,name', 'booking.tour:id,uuid,title'])->latest()->paginate($perPage);

        return $this->success([
            'data' => TourReviewResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
