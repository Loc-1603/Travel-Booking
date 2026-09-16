<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Http\Requests\Api\StoreTourBookingRequest;
use App\Http\Requests\Api\StoreTourDisputeRequest;
use App\Http\Resources\TourBookingResource;
use App\Http\Resources\TourDisputeResource;
use App\Models\TourBooking;
use App\Models\TourDispute;
use App\Services\TourBookingService;
use App\Services\TourPaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TourBookingController extends BaseApiController
{
    public function __construct(
        protected TourBookingService $bookings,
        protected TourPaymentService $payments,
    ) {}

    /**
     * Preview price (no booking created, no slot lock).
     */
    public function preview(StoreTourBookingRequest $request): JsonResponse
    {
        $breakdown = $this->bookings->preview(
            (int) $request->tour_id,
            (int) $request->slot_id,
            $request->pricing_mode,
            (int) $request->duration_value,
            $request->filled('coupon_code') ? trim($request->coupon_code) : null,
            (int) $request->user()->id,
        );

        return $this->success([
            'subtotal' => $breakdown->subtotal,
            'discount' => $breakdown->discount,
            'tax' => $breakdown->tax,
            'tax_name' => $breakdown->taxName,
            'tax_inclusive' => $breakdown->taxInclusive,
            'add_on_amount' => $breakdown->addOnAmount,
            'total' => $breakdown->total,
            'currency' => $breakdown->currency,
            'coupon_code' => $breakdown->couponCode,
            'coupon_applied' => $breakdown->couponId !== null,
        ]);
    }

    /**
     * Create tour booking (auth required, no guest flow): lock slot -> create.
     */
    public function store(StoreTourBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->bookings->createBooking(
                (int) $request->user()->id,
                (int) $request->tour_id,
                (int) $request->slot_id,
                $request->pricing_mode,
                (int) $request->duration_value,
                $request->filled('coupon_code') ? trim($request->coupon_code) : null,
                $request->input('meeting_point'),
                $request->input('customer_notes'),
                $request->input('currency', config('tour.currency', 'VND')),
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422, 'UNAVAILABLE');
        }

        return $this->success([
            'booking' => new TourBookingResource($booking->load(['tour.provider', 'tour.province', 'slot'])),
        ], 201);
    }

    /**
     * Preview giá 1 ngày theo Guide (không tạo booking, không lock slot).
     */
    public function previewGuide(\App\Http\Requests\Api\StoreGuideBookingRequest $request): JsonResponse
    {
        try {
            $slotId = $request->filled('slot_id') ? (int) $request->input('slot_id') : null;
            $breakdown = $this->bookings->previewGuide(
                $request->input('provider_uuid'),
                $slotId,
                $request->filled('coupon_code') ? trim($request->coupon_code) : null,
                (int) $request->user()->id,
                $request->input('travel_date'),
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422, 'UNAVAILABLE');
        }

        return $this->success([
            'subtotal' => $breakdown->subtotal,
            'discount' => $breakdown->discount,
            'tax' => $breakdown->tax,
            'tax_name' => $breakdown->taxName,
            'tax_inclusive' => $breakdown->taxInclusive,
            'add_on_amount' => $breakdown->addOnAmount,
            'total' => $breakdown->total,
            'currency' => $breakdown->currency,
            'coupon_code' => $breakdown->couponCode,
            'coupon_applied' => $breakdown->couponId !== null,
            'pricing_mode' => 'day',
            'duration_value' => 1,
        ]);
    }

    /**
     * Tạo booking 1 ngày theo Guide (auth required): lock slot -> create.
     */
    public function storeGuide(\App\Http\Requests\Api\StoreGuideBookingRequest $request): JsonResponse
    {
        try {
            $slotId = $request->filled('slot_id') ? (int) $request->input('slot_id') : null;
            $booking = $this->bookings->createGuideBooking(
                (int) $request->user()->id,
                $request->input('provider_uuid'),
                $slotId,
                $request->input('travel_date'),
                $request->filled('coupon_code') ? trim($request->coupon_code) : null,
                $request->input('meeting_point'),
                $request->input('customer_notes'),
                $request->input('currency', config('tour.currency', 'VND')),
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422, 'UNAVAILABLE');
        }

        return $this->success([
            'booking' => new TourBookingResource($booking->load(['tour.provider', 'tour.province', 'slot'])),
        ], 201);
    }

    /**
     * Customer tour booking history (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $paginator = TourBooking::where('customer_id', $request->user()->id)
            ->with(['tour.provider', 'tour.province', 'slot'])
            ->withCount('review')
            ->orderByDesc('start_at')
            ->latest()
            ->paginate($perPage);

        return $this->success([
            'data' => TourBookingResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $booking = TourBooking::where('uuid', $uuid)
            ->with(['tour.provider', 'tour.province', 'slot', 'tour.images'])
            ->withCount('review')
            ->firstOrFail();
        $this->authorize('view', $booking);

        return $this->success(new TourBookingResource($booking));
    }

    /**
     * Create VNPay payment URL for a pending tour booking (owner only).
     */
    public function createCheckoutSession(Request $request, string $uuid): JsonResponse
    {
        $booking = TourBooking::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $booking);

        if ($booking->status !== TourBookingStatus::PENDING_PAYMENT->value) {
            return $this->error('This booking is not awaiting payment.', 422, 'INVALID_STATUS');
        }

        try {
            $result = $this->payments->createCheckoutSession($booking, $request->ip());

            return $this->success($result);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500, 'CHECKOUT_FAILED');
        }
    }

    /**
     * Cancel booking: release slot, refund completed tour payments.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $booking = TourBooking::where('uuid', $uuid)->with('payments')->firstOrFail();
        $this->authorize('cancel', $booking);

        try {
            $this->bookings->cancelBooking($booking);
            foreach ($booking->payments as $payment) {
                if ($payment->status === PaymentStatus::COMPLETED->value) {
                    $this->payments->refund($payment, (float) $payment->amount - (float) ($payment->refunded_amount ?? 0), 'cancelled_by_customer');
                }
            }
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422, 'CANCEL_FAILED');
        }

        return $this->success(new TourBookingResource($booking->fresh()->load(['tour.provider', 'tour.province', 'slot'])));
    }

    /**
     * Customer: open a dispute (auth required, owns booking, no guest flow).
     */
    public function storeDispute(StoreTourDisputeRequest $request, string $uuid): JsonResponse
    {
        $booking = TourBooking::where('uuid', $uuid)->with('dispute')->firstOrFail();
        $this->authorize('dispute', $booking);

        if (! $booking->canOpenDispute()) {
            if ($booking->status === TourBookingStatus::PENDING_PAYMENT->value) {
                return $this->error('Disputes are available after payment is completed.', 422, 'INVALID_STATUS');
            }

            return $this->error('A dispute already exists for this booking.', 422, 'DISPUTE_EXISTS');
        }

        $user = $request->user();

        try {
            $dispute = TourDispute::create([
                'tour_booking_id' => $booking->id,
                'status' => 'open',
                'contact_name' => $request->input('contact_name') ?: $user->name,
                'contact_email' => $request->input('contact_email') ?: $user->email,
                'contact_phone' => $request->input('contact_phone'),
                'customer_notes' => $request->input('customer_notes'),
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'tour_disputes_tour_booking_id_unique')
                || str_contains($e->getMessage(), 'UNIQUE constraint failed: tour_disputes.tour_booking_id')) {
                return $this->error('A dispute already exists for this booking.', 422, 'DISPUTE_EXISTS');
            }
            throw $e;
        }

        return $this->success(new TourDisputeResource($dispute), 201);
    }

    /**
     * Download invoice/receipt (HTML). Customer: own bookings; Vendor: own provider's bookings.
     */
    public function invoice(Request $request, string $uuid): Response|JsonResponse
    {
        $booking = TourBooking::where('uuid', $uuid)
            ->with(['tour.provider', 'tour.province', 'slot', 'customer'])
            ->firstOrFail();
        $this->authorize('view', $booking);

        $html = view('invoice.tour_booking', ['booking' => $booking])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="invoice-'.$booking->uuid.'.html"',
        ]);
    }
}
