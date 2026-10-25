<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\CancellationPolicyService;
use App\Services\RefundService;

/**
 * Standard formula for cancelling a hotel booking (Phase 2 / A-2).
 *
 * Compute refund from policy -> release inventory via BookingService ->
 * spread the refund across completed payments via RefundService.
 * Auth (owner check) and HTTP mapping stay in the controller until Phase 4.
 */
class CancelBookingAction
{
    public function __construct(
        protected BookingService $bookings,
        protected CancellationPolicyService $policies,
        protected RefundService $refunds,
    ) {}

    public function handle(Booking $booking): Booking
    {
        $refundAmount = $this->policies->getRefundAmount($booking);

        $this->bookings->cancelBooking($booking);

        if ($refundAmount > 0) {
            foreach ($booking->payments as $payment) {
                if ($payment->status === PaymentStatus::COMPLETED->value && $payment->external_id) {
                    $refundable = (float) $payment->amount - (float) ($payment->refunded_amount ?? 0);
                    if ($refundable > 0) {
                        $toRefund = min($refundAmount, $refundable);
                        $this->refunds->request(
                            $payment,
                            $toRefund,
                            'requested_by_customer',
                            "hotel-cancel:{$booking->id}:{$payment->id}",
                            auth()->id(),
                        );
                        $refundAmount -= $toRefund;
                        if ($refundAmount <= 0) {
                            break;
                        }
                    }
                }
            }
        }

        return $booking->fresh()->load(['hotel', 'bookingRooms.room', 'coupon', 'dispute']);
    }
}
