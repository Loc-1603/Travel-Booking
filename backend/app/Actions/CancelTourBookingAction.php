<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Models\TourBooking;
use App\Services\TourBookingService;
use App\Services\TourPaymentService;

/**
 * Standard formula for cancelling a tour booking (Phase 2 / A-3).
 */
class CancelTourBookingAction
{
    public function __construct(
        protected TourBookingService $bookings,
        protected TourPaymentService $payments,
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
                    $this->payments->refund($payment, $refundable, 'cancelled_by_customer');
                }
            }
        }

        return $booking->fresh()->load(['tour', 'slot', 'payments']);
    }
}
