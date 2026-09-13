<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Events\TourPaymentConfirmed;
use App\Models\TourPayment;
use Illuminate\Support\Facades\DB;

class TourPaymentService
{
    public function __construct(
        protected TourVnpayAdapter $vnpay,
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
    public function confirmPayment(TourPayment $payment): void
    {
        if ($payment->status === PaymentStatus::COMPLETED->value) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => PaymentStatus::COMPLETED->value]);
            $booking = $payment->booking;
            if ($booking && $booking->status !== TourBookingStatus::CONFIRMED->value) {
                $booking->update(['status' => TourBookingStatus::CONFIRMED->value]);
            }
        });

        event(new TourPaymentConfirmed($payment));
    }

    public function markFailed(TourPayment $payment, ?string $note = null): void
    {
        if (in_array($payment->status, [PaymentStatus::COMPLETED->value, PaymentStatus::REFUNDED->value], true)) {
            return;
        }

        $payload = $payment->payload ?? [];
        if ($note !== null) {
            $payload['failure_note'] = $note;
        }
        $payment->update(['status' => PaymentStatus::FAILED->value, 'payload' => $payload]);
    }

    public function refund(TourPayment $payment, float $amount, ?string $reason = null): void
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
        $payment->save();
    }

    /**
     * Full refund for a tour payment (convenience).
     */
    public function refundFull(TourPayment $payment, ?string $reason = null): void
    {
        $amount = (float) $payment->amount - (float) ($payment->refunded_amount ?? 0);
        if ($amount > 0) {
            $this->refund($payment, $amount, $reason);
        }
    }
}
