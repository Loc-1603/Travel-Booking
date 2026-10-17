<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TourBookingStatus;
use App\Http\Requests\Api\SubmitReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Review;
use App\Models\TourBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Đánh giá dùng chung cho hotel booking (booking_id) và tour/guide booking (tour_booking_id).
 * Review hiển thị ngay sau khi gửi; admin chỉ hậu kiểm (ẩn/hiện).
 */
class ReviewController extends BaseApiController
{
    /**
     * Submit review: one review per booking; verified by booking ownership.
     */
    public function store(SubmitReviewRequest $request): JsonResponse
    {
        if ($request->filled('tour_booking_id')) {
            return $this->storeTourReview($request);
        }

        $booking = Booking::where('id', $request->booking_id)
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        if (! in_array($booking->status, ['completed', 'confirmed'], true)) {
            return $this->error('Review only allowed for completed or confirmed bookings.', 422, 'INVALID_BOOKING_STATE');
        }

        if ($booking->review()->exists()) {
            return $this->error('One review per booking.', 422, 'REVIEW_EXISTS');
        }

        $review = DB::transaction(function () use ($request, $booking) {
            $created = Review::create([
                'booking_id' => $booking->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            $this->storeImages($request, $created);

            return $created;
        });

        return $this->success(new ReviewResource($review->load('images')), 201);
    }

    /**
     * List reviews: filter by hotel, hotel booking, tour uuid, guide (provider) uuid or tour booking id.
     * Chỉ trả review đang hiển thị (hidden = false). Paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'hotel_id' => 'nullable|exists:hotels,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'tour_uuid' => 'nullable|string',
            'provider_uuid' => 'nullable|string',
            'tour_booking_id' => 'nullable|exists:tour_bookings,id',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Review::query()->visible();

        if ($request->filled('hotel_id')) {
            $query->forHotel((int) $request->hotel_id);
        }
        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }
        if ($request->filled('tour_uuid')) {
            $query->forTour($request->tour_uuid);
        }
        if ($request->filled('provider_uuid')) {
            $query->forProvider($request->provider_uuid);
        }
        if ($request->filled('tour_booking_id')) {
            $query->where('tour_booking_id', $request->tour_booking_id);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query
            ->with(['images', 'booking.customer:id,name', 'tourBooking.customer:id,name', 'tourBooking.tour:id,uuid,title'])
            ->latest()
            ->paginate($perPage);

        return $this->success([
            'data' => ReviewResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function storeTourReview(SubmitReviewRequest $request): JsonResponse
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

        $review = DB::transaction(function () use ($request, $booking) {
            $created = Review::create([
                'tour_booking_id' => $booking->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            $this->storeImages($request, $created);

            return $created;
        });

        return $this->success(new ReviewResource($review->load('images')), 201);
    }

    private function storeImages(SubmitReviewRequest $request, Review $review): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        foreach ($request->file('images') as $i => $file) {
            $path = $file->store('review-images', 'public');
            $review->images()->create(['path' => $path, 'sort_order' => $i]);
        }
    }
}
