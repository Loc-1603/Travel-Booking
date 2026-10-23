<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Events\TourPaymentConfirmed;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TourPaymentService
{
    public function __construct(
        protected TourVnpayAdapter $vnpay,
        protected TourPaymentRepositoryInterface $payments,
        protected TourBookingRepositoryInterface $bookings,
    ) {}

    /**
     * @return array{checkout_url: string, payment_id: int, txn_ref: string}
     */
    public function createCheckoutSession(\App\Models\TourBooking $booking, ?string $ipAddr = null, ?string $bankCode = null): array
    {
        $result = $this->vnpay->createPaymentUrl($booking, $ipAddr, $bankCode);

        return [
            'checkout_url' => $result['payment_url'],
            'payment_id' => $result['payment_id'],
            'txn_ref' => $result['txn_ref'],
        ];
    }

    /**
     * Confirm payment + booking after successful VNPay IPN. Idempotent.
     */
    public function confirmPayment(\App\Models\TourPayment $payment): void
    {
        if ($payment->status === PaymentStatus::COMPLETED->value) {
            return;
        }

        $confirmed = DB::transaction(function () use ($payment) {
            $payment = $this->payments->update($payment, ['status' => PaymentStatus::COMPLETED->value]);
            $booking = $payment->tour_booking_id !== null
                ? $this->bookings->findById((int) $payment->tour_booking_id)
                : null;
            if ($booking && $booking->status !== TourBookingStatus::CONFIRMED->value) {
                $this->bookings->update($booking, ['status' => TourBookingStatus::CONFIRMED->value]);
            }

            return $payment;
        });

        event(new TourPaymentConfirmed($confirmed));
    }

    public function markFailed(\App\Models\TourPayment $payment, ?string $note = null): void
    {
        if (in_array($payment->status, [PaymentStatus::COMPLETED->value, PaymentStatus::REFUNDED->value], true)) {
            return;
        }

        $payload = $payment->payload ?? [];
        if ($note !== null) {
            $payload['failure_note'] = $note;
        }
        $this->payments->update($payment, ['status' => PaymentStatus::FAILED->value, 'payload' => $payload]);
    }

    public function refund(\App\Models\TourPayment $payment, float $amount, ?string $reason = null): void
    {
        $maxRefundable = (float) $payment->amount - (float) ($payment->refunded_amount ?? 0);
        if ($amount <= 0 || $amount > $maxRefundable) {
            throw new \InvalidArgumentException("Refund amount must be between 0 and {$maxRefundable}.");
        }

        $newRefunded = (float) ($payment->refunded_amount ?? 0) + $amount;
        $payment->refunded_amount = $newRefunded;
        $payment->status = $newRefunded >= (float) $payment->amount
            ? PaymentStatus::REFUNDED->value
            : PaymentStatus::COMPLETED->value;

        $payload = $payment->payload ?? [];
        $payload['refunds'][] = [
            'amount' => $amount,
            'reason' => $reason,
            'refunded_at' => now()->toIso8601String(),
        ];
        $payment->payload = $payload;
        $this->payments->update($payment, [
            'refunded_amount' => $newRefunded,
            'status' => $payment->status,
            'payload' => $payload,
        ]);
    }

    /**
     * Full refund for a tour payment (convenience).
     */
    public function refundFull(\App\Models\TourPayment $payment, ?string $reason = null): void
    {
        $amount = (float) $payment->amount - (float) ($payment->refunded_amount ?? 0);
        if ($amount > 0) {
            $this->refund($payment, $amount, $reason);
        }
    }
}
