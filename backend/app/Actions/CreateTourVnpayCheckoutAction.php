<?php

namespace App\Actions;

use App\DTOs\TourCheckoutData;
use App\Enums\TourBookingStatus;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Services\TourPaymentService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Standard formula for creating a VNPay checkout session for a tour booking (Phase 2 / A-4).
 */
class CreateTourVnpayCheckoutAction
{
    public function __construct(
        protected TourBookingRepositoryInterface $bookings,
        protected TourPaymentService $payments,
    ) {}

    /**
     * @return array{checkout_url: string, payment_id: int, txn_ref: string}
     */
    public function handle(TourCheckoutData $data): array
    {
        $booking = $this->bookings->findById($data->bookingId);
        if (! $booking) {
            throw new InvalidArgumentException('Tour booking not found.');
        }
        if ($booking->status !== TourBookingStatus::PENDING_PAYMENT->value) {
            throw new RuntimeException('This booking is not awaiting payment.');
        }

        return $this->payments->createCheckoutSession($booking, $data->ipAddr, $data->bankCode);
    }
}
