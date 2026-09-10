<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentConfirmed;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected VnpayService $vnpay,
    ) {}

    /**
     * Create a VNPay payment URL for a pending booking.
     * Returns redirect payload for frontend.
     *
     * @return array{checkout_url: string, payment_id: int, txn_ref: string}
     */
    public function createCheckoutSession(Booking $booking, ?string $ipAddr = null, ?string $bankCode = null): array
    {
        $result = $this->vnpay->createPaymentUrl($booking, $ipAddr, $bankCode);

        return [
            'checkout_url' => $result['payment_url'],
            'payment_id' => $result['payment_id'],
            'txn_ref' => $result['txn_ref'],
        ];
    }

    /**
     * Confirm payment and booking after successful VNPay IPN. Idempotent; call from job.
     */
    public function confirmPayment(Payment $payment): void
    {
        if ($payment->status === PaymentStatus::COMPLETED->value) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => PaymentStatus::COMPLETED->value]);
            $booking = $payment->booking;
            if ($booking && $booking->status !== BookingStatus::CONFIRMED->value) {
                $booking->update(['status' => BookingStatus::CONFIRMED->value]);
            }
        });

        event(new PaymentConfirmed($payment));
    }

    /**
     * Mark a payment as failed (e.g. VNPay return with error code, user cancelled).
     * Booking stays payable so the customer can retry.
     */
    public function markFailed(Payment $payment, ?string $note = null): void
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

    /**
     * Record a refund (manual / via VNPay merchant portal).
     * VNPay refunds are settled outside the checkout API, so this only
     * updates local accounting (refunded_amount + status).
     *
     * @param  float  $amount  Amount to refund in booking currency (VND)
     */
    public function refund(Payment $payment, float $amount, ?string $reason = null, ?string $idempotencyKey = null): void
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
     * Full refund for a payment (convenience).
     */
    public function refundFull(Payment $payment, ?string $reason = null, ?string $idempotencyKey = null): void
    {
        $amount = (float) $payment->amount - (float) ($payment->refunded_amount ?? 0);
        if ($amount > 0) {
            $this->refund($payment, $amount, $reason, $idempotencyKey);
        }
    }
}
