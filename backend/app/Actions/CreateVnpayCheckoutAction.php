<?php

namespace App\Actions;

use App\DTOs\CheckoutData;
use App\Enums\BookingStatus;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\PaymentService;

/**
 * Standard formula for creating a VNPay checkout session (Phase 2 / A-3).
 *
 * Loads the booking via Repository, enforces pending-payment status
 * (currently in BookingController@createCheckoutSession), then delegates
 * URL building + pending Payment persistence to PaymentService.
 * Auth (owner/guest-signature) and HTTP mapping stay in the controller
 * until Phase 4.
 *
 * @return array{checkout_url: string, payment_id: int, txn_ref: string}
 */
class CreateVnpayCheckoutAction
{
    public function __construct(
        protected BookingRepositoryInterface $bookings,
        protected PaymentService $payments,
    ) {}

    public function handle(CheckoutData $data): array
    {
        $booking = $this->bookings->findById($data->bookingId, ['hotel']);
        if (! $booking) {
            throw new \InvalidArgumentException('Booking not found.');
        }
        if ($booking->status !== BookingStatus::PENDING_PAYMENT->value) {
            throw new \RuntimeException('This booking is not awaiting payment.');
        }

        return $this->payments->createCheckoutSession($booking, $data->ipAddr, $data->bankCode);
    }
}
