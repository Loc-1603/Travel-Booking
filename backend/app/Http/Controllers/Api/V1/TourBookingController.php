<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelTourBookingAction;
use App\Actions\CreateGuideTourBookingAction;
use App\Actions\CreateTourBookingAction;
use App\Actions\CreateTourVnpayCheckoutAction;
use App\Actions\OpenTourDisputeAction;
use App\DTOs\CreateGuideTourBookingData;
use App\DTOs\CreateTourBookingData;
use App\DTOs\TourCheckoutData;
use App\Enums\TourBookingStatus;
use App\Http\Requests\Api\StoreTourBookingRequest;
use App\Http\Requests\Api\StoreTourDisputeRequest;
use App\Http\Resources\TourBookingResource;
use App\Http\Resources\TourDisputeResource;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Services\TourBookingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TourBookingController extends BaseApiController
{
    public function __construct(
        protected TourBookingService $bookings,
        protected TourBookingRepositoryInterface $bookingRepo,
        protected CreateTourBookingAction $createBookingAction,
        protected CreateGuideTourBookingAction $createGuideBookingAction,
        protected CancelTourBookingAction $cancelAction,
        protected CreateTourVnpayCheckoutAction $checkoutAction,
        protected OpenTourDisputeAction $disputeAction,
    ) {}

    /**
     * Preview price (public cho guest, không tạo booking/lock slot — BE-1.4).
     */
    public function preview(StoreTourBookingRequest $request): JsonResponse
    {
        $breakdown = $this->bookings->preview(
            (int) $request->tour_id,
            (int) $request->slot_id,
            $request->pricing_mode,
            (int) $request->duration_value,
            $request->filled('coupon_code') ? trim($request->coupon_code) : null,
            $request->user()?->id,
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
            $booking = $this->createBookingAction->handle(new CreateTourBookingData(
                customerId: (int) $request->user()->id,
                tourId: (int) $request->tour_id,
                slotId: (int) $request->slot_id,
                pricingMode: $request->pricing_mode,
                durationValue: (int) $request->duration_value,
                couponCode: $request->filled('coupon_code') ? trim($request->coupon_code) : null,
                meetingPoint: $request->input('meeting_point'),
                customerNotes: $request->input('customer_notes'),
                currency: $request->input('currency', config('tour.currency', 'VND')),
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, 'UNAVAILABLE');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422, 'UNAVAILABLE');
        }

        return $this->success([
            'booking' => new TourBookingResource($booking->load(['tour.provider', 'tour.province', 'slot'])),
        ], 201);
    }

    /**
     * Preview giá 1 ngày theo Guide (public cho guest — BE-1.4).
     */
    public function previewGuide(\App\Http\Requests\Api\StoreGuideBookingRequest $request): JsonResponse
    {
        try {
            $slotId = $request->filled('slot_id') ? (int) $request->input('slot_id') : null;
            $breakdown = $this->bookings->previewGuide(
                $request->input('provider_uuid'),
                $slotId,
                $request->filled('coupon_code') ? trim($request->coupon_code) : null,
                $request->user()?->id,
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
            $booking = $this->createGuideBookingAction->handle(new CreateGuideTourBookingData(
                customerId: (int) $request->user()->id,
                providerUuid: $request->input('provider_uuid'),
                slotId: $slotId,
                travelDate: $request->input('travel_date'),
                couponCode: $request->filled('coupon_code') ? trim($request->coupon_code) : null,
                meetingPoint: $request->input('meeting_point'),
                customerNotes: $request->input('customer_notes'),
                currency: $request->input('currency', config('tour.currency', 'VND')),
            ));
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
        $paginator = $this->bookingRepo->paginateByCustomer($request->user()->id, ['tour.provider', 'tour.province', 'slot'], $perPage);

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
        $booking = $this->bookingRepo->findByUuidOrFail($uuid, ['tour.provider', 'tour.province', 'slot', 'tour.images']);
        $booking->loadCount('review');
        $this->authorize('view', $booking);

        return $this->success(new TourBookingResource($booking));
    }

    /**
     * Create VNPay payment URL for a pending tour booking (owner only).
     */
    public function createCheckoutSession(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookingRepo->findByUuid($uuid);
        if (! $booking) {
            return $this->error('Tour booking not found.', 404, 'NOT_FOUND');
        }
        $this->authorize('view', $booking);

        if ($booking->status !== TourBookingStatus::PENDING_PAYMENT->value) {
            return $this->error('This booking is not awaiting payment.', 422, 'INVALID_STATUS');
        }

        try {
            $result = $this->checkoutAction->handle(new TourCheckoutData(
                bookingId: $booking->id,
                ipAddr: $request->ip(),
                bankCode: $request->input('bank_code'),
            ));

            return $this->success($result);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 404, 'NOT_FOUND');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500, 'CHECKOUT_FAILED');
        }
    }

    /**
     * Cancel booking: release slot, refund completed tour payments.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookingRepo->findByUuid($uuid, ['payments']);
        if (! $booking) {
            return $this->error('Tour booking not found.', 404, 'NOT_FOUND');
        }
        $this->authorize('cancel', $booking);

        try {
            $booking = $this->cancelAction->handle($booking);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422, 'CANCEL_FAILED');
        }

        return $this->success(new TourBookingResource($booking->load(['tour.provider', 'tour.province', 'slot'])));
    }

    /**
     * Customer: open a dispute (auth required, owns booking, no guest flow).
     */
    public function storeDispute(StoreTourDisputeRequest $request, string $uuid): JsonResponse
    {
        $booking = $this->bookingRepo->findByUuid($uuid, ['dispute']);
        if (! $booking) {
            return $this->error('Tour booking not found.', 404, 'NOT_FOUND');
        }
        $this->authorize('dispute', $booking);

        if (! $booking->canOpenDispute()) {
            if ($booking->status === TourBookingStatus::PENDING_PAYMENT->value) {
                return $this->error('Disputes are available after payment is completed.', 422, 'INVALID_STATUS');
            }

            return $this->error('A dispute already exists for this booking.', 422, 'DISPUTE_EXISTS');
        }

        $user = $request->user();

        try {
            $dispute = $this->disputeAction->handle($booking, $user, [
                'contact_name' => $request->input('contact_name'),
                'contact_email' => $request->input('contact_email'),
                'contact_phone' => $request->input('contact_phone'),
                'customer_notes' => $request->input('customer_notes'),
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'tour_disputes_tour_booking_id_unique')
                || str_contains($e->getMessage(), 'UNIQUE constraint failed: tour_disputes.tour_booking_id')) {
                return $this->error('A dispute already exists for this booking.', 422, 'DISPUTE_EXISTS');
            }
            throw $e;
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422, 'INVALID_STATUS');
        }

        return $this->success(new TourDisputeResource($dispute), 201);
    }

    /**
     * Download invoice/receipt (HTML). Customer: own bookings; Vendor: own provider's bookings.
     */
    public function invoice(Request $request, string $uuid): Response|JsonResponse
    {
        $booking = $this->bookingRepo->findByUuid($uuid, ['tour.provider', 'tour.province', 'slot', 'customer']);
        if (! $booking) {
            return $this->error('Tour booking not found.', 404, 'NOT_FOUND');
        }
        $this->authorize('view', $booking);

        $html = view('invoice.tour_booking', ['booking' => $booking])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="invoice-'.$booking->uuid.'.html"',
        ]);
    }
}
