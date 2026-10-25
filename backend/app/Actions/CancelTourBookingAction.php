<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Models\TourBooking;
use App\Services\RefundService;
use App\Services\TourBookingService;

/**
 * Standard formula for cancelling a tour booking (Phase 2 / A-3).
 */
class CancelTourBookingAction
{
    public function __construct(
        protected TourBookingService $bookings,
        protected RefundService $refunds,
    ) {}

    public function handle(TourBooking $booking): TourBooking
    {
        if ($booking->status !== TourBookingStatus::CANCELLED->value) {
            $this->bookings->cancelBooking($booking);
        }

        foreach ($booking->payments as $payment) {
            if ($payment->status === PaymentStatus::COMPLETED->value) {
                $refundable = (float) $payment->amount - (float) ($payment->refunded_amount ?? 0);
                if ($refundable > 0) {
                    $this->refunds->request(
                        $payment,
                        $refundable,
                        'cancelled_by_customer',
                        "tour-cancel:{$booking->id}:{$payment->id}",
                        auth()->id(),
                    );
                }
            }
        }

        return $booking->fresh()->load(['tour', 'slot', 'payments']);
    }
}
