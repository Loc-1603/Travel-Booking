<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelBookingAction;
use App\Actions\ClaimBookingAction;
use App\Actions\CreateBookingAction;
use App\Actions\CreateVnpayCheckoutAction;
use App\DTOs\CheckoutData;
use App\DTOs\CreateBookingData;
use App\Events\BookingDisputeCreated;
use App\Http\Requests\Api\CreateBookingRequest;
use App\Http\Requests\Api\StoreBookingDisputeRequest;
use App\Http\Resources\BookingDisputeResource;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingDispute;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Services\PricingService;
use App\Support\BookingInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookingController extends BaseApiController
{
    public function __construct(
        protected BookingRepositoryInterface $bookings,
        protected RoomRepositoryInterface $rooms,
        protected PricingService $pricingService,
        protected CreateBookingAction $createBooking,
        protected CancelBookingAction $cancelBooking,
        protected ClaimBookingAction $claimBooking,
        protected CreateVnpayCheckoutAction $createCheckout,
    ) {}

    /**
     * Preview price breakdown (no booking created). Same body as store; returns subtotal, discount, total.
     */
    public function preview(CreateBookingRequest $request): JsonResponse
    {
        $user = $request->user();
        $hotelId = (int) $request->hotel_id;
        $checkIn = $request->check_in;
        $checkOut = $request->check_out;
        $currency = $request->input('currency', 'VND');

        $roomQuantities = [];
        foreach ($request->rooms as $item) {
            $roomId = (int) $item['room_id'];
            $room = $this->rooms->findInHotel($roomId, $hotelId);
            if (! $room) {
                return $this->error('Room does not belong to the selected hotel.', 422, 'INVALID_ROOM');
            }
            $roomQuantities[$roomId] = (int) $item['quantity'];
        }

        $couponCode = $request->filled('coupon_code') ? trim($request->coupon_code) : null;
        $lateCheckout = (bool) $request->input('late_checkout', false);
        $breakdown = $this->pricingService->calculate(
            $roomQuantities,
            $checkIn,
            $checkOut,
            $hotelId,
            $couponCode,
            (int) $user->id,
            $lateCheckout,
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
     * Create booking: validate, lock, create; return booking + payment intent.
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        $user = $request->user();
        $hotelId = (int) $request->hotel_id;
        $checkIn = $request->check_in;
        $checkOut = $request->check_out;
        $currency = $request->input('currency', 'VND');

        $roomQuantities = [];
        foreach ($request->rooms as $item) {
            $roomId = (int) $item['room_id'];
            $room = $this->rooms->findInHotel($roomId, $hotelId);
            if (! $room) {
                return $this->error('Room does not belong to the selected hotel.', 422, 'INVALID_ROOM');
            }
            $roomQuantities[$roomId] = (int) $item['quantity'];
        }

        $couponCode = $request->filled('coupon_code') ? trim($request->coupon_code) : null;

        try {
            $booking = $this->createBooking->handle(new CreateBookingData(
                customerId: (int) $user->id,
                hotelId: $hotelId,
                roomQuantities: $roomQuantities,
                checkIn: $checkIn,
                checkOut: $checkOut,
                currency: $currency,
                couponCode: $couponCode,
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, 'INVALID_REQUEST');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422, 'UNAVAILABLE');
        }

        return $this->success([
            'booking' => new BookingResource($booking->load(['hotel', 'bookingRooms.room', 'coupon', 'dispute'])),
        ], 201);
    }

    /**
     * Create VNPay payment URL for a pending booking. Returns checkout_url for redirect.
     * Auth: customer (own booking) or guest (valid signed URL).
     */
    public function createCheckoutSession(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookings->findByUuidOrFail($uuid, ['hotel']);

        $user = $request->user();
        $isOwner = $user && $booking->customer_id !== null && (int) $booking->customer_id === (int) $user->id;
        $isGuest = ! $user && $booking->isGuest() && $request->hasValidSignature();
        if (! $isOwner && ! $isGuest) {
            return $this->error('You do not have access to this booking.', 403, 'FORBIDDEN');
        }

        if ($booking->status !== \App\Enums\BookingStatus::PENDING_PAYMENT->value) {
            return $this->error('This booking is not awaiting payment.', 422, 'INVALID_STATUS');
        }

        try {
            $result = $this->createCheckout->handle(new CheckoutData(
                bookingId: $booking->id,
                ipAddr: $request->ip(),
            ));

            return $this->success($result);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, 'INVALID_STATUS');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500, 'CHECKOUT_FAILED');
        }
    }

    /**
     * Claim a guest booking: attach to the current user. Booking guest_email must match user email.
     */
    public function claim(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookings->findByUuidOrFail($uuid, ['hotel', 'bookingRooms.room', 'coupon']);
        $user = $request->user();

        try {
            $booking = $this->claimBooking->handle($booking, (int) $user->id, (string) $user->email);
        } catch (\DomainException $e) {
            $code = match (true) {
                str_contains($e->getMessage(), 'already linked') => 'ALREADY_CLAIMED',
                str_contains($e->getMessage(), 'cannot be claimed') => 'NOT_GUEST',
                default => 'EMAIL_MISMATCH',
            };

            return $this->error($e->getMessage(), $code === 'EMAIL_MISMATCH' ? 403 : 422, $code);
        }

        return $this->success(new BookingResource($booking));
    }

    /**
     * Customer: open a dispute (logged-in, owns booking).
     */
    public function storeDispute(StoreBookingDisputeRequest $request, string $uuid): JsonResponse
    {
        $booking = $this->bookings->findByUuidOrFail($uuid, ['dispute']);
        $this->authorize('dispute', $booking);

        return $this->createDisputeAndRespond($request, $booking);
    }

    private function createDisputeAndRespond(StoreBookingDisputeRequest $request, Booking $booking): JsonResponse
    {
        if (! $booking->canOpenDispute()) {
            if ($booking->status === \App\Enums\BookingStatus::PENDING_PAYMENT->value) {
                return $this->error('Disputes are available after payment is completed.', 422, 'INVALID_STATUS');
            }

            return $this->error('A dispute already exists for this booking.', 422, 'DISPUTE_EXISTS');
        }

        $user = $request->user();
        $contactName = $request->input('contact_name') ?: ($user?->name ?? $booking->guest_name);
        $contactEmail = $request->input('contact_email') ?: ($user?->email ?? $booking->guest_email);
        if (empty($contactEmail)) {
            return $this->error('Contact email is required.', 422, 'MISSING_CONTACT');
        }
        if (empty($contactName)) {
            $contactName = 'Guest';
        }

        try {
            $dispute = BookingDispute::create([
                'booking_id' => $booking->id,
                'status' => 'open',
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'contact_phone' => $request->input('contact_phone'),
                'customer_notes' => $request->input('customer_notes'),
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'booking_disputes_booking_id_unique')
                || str_contains($e->getMessage(), 'UNIQUE constraint failed: booking_disputes.booking_id')) {
                return $this->error('A dispute already exists for this booking.', 422, 'DISPUTE_EXISTS');
            }
            throw $e;
        }

        event(new BookingDisputeCreated($dispute));

        return $this->success(new BookingDisputeResource($dispute), 201);
    }

    /**
     * Download invoice/receipt for a booking (HTML). Customer: own bookings; Vendor: own hotel's bookings.
     */
    public function invoice(Request $request, string $uuid): Response|JsonResponse
    {
        $booking = $this->bookings->findByUuidOrFail($uuid, ['hotel', 'bookingRooms.room', 'customer', 'coupon']);
        $user = $request->user();
        $isCustomer = $booking->customer_id !== null && (int) $booking->customer_id === (int) $user->id;
        $isVendor = $booking->hotel && (int) $booking->hotel->vendor_id === (int) $user->id;
        if (! $isCustomer && ! $isVendor) {
            return $this->error('You do not have access to this invoice.', 403, 'FORBIDDEN');
        }

        $nights = $booking->check_in->diffInDays($booking->check_out);
        $subtotal = (float) $booking->total_price - (float) ($booking->tax_amount ?? 0) + (float) ($booking->discount_amount ?? 0);

        $html = view('invoice.booking', BookingInvoice::viewData($booking, $nights, round($subtotal, 2)))->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="invoice-'.$booking->uuid.'.html"',
        ]);
    }

    /**
     * Confirm booking (poll status after payment). Returns current booking state.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookings->findByUuidOrFail($uuid, ['hotel', 'bookingRooms.room', 'review', 'coupon', 'dispute']);
        $user = $request->user();
        $isOwner = $booking->customer_id !== null && (int) $booking->customer_id === (int) $user->id;
        if (! $isOwner) {
            return $this->error('You do not have access to this booking.', 403, 'FORBIDDEN');
        }

        return $this->success(new BookingResource($booking));
    }

    /**
     * Cancel booking. Releases inventory, then refunds any completed payment via PaymentService.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $booking = $this->bookings->findByUuidOrFail($uuid, ['payments']);
        $isOwner = $booking->customer_id !== null && (int) $booking->customer_id === (int) $request->user()->id;
        if (! $isOwner) {
            return $this->error('You do not have access to this booking.', 403, 'FORBIDDEN');
        }
        try {
            $booking = $this->cancelBooking->handle($booking);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422, 'CANCEL_FAILED');
        }

        return $this->success(new BookingResource($booking));
    }

    /**
     * Customer booking history (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $paginator = $this->bookings->paginateByCustomer(
            $request->user()->id,
            ['hotel', 'bookingRooms.room', 'coupon', 'dispute', 'review'],
            $perPage,
        );

        return $this->success([
            'data' => BookingResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
