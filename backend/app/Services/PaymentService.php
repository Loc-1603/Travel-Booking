<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentConfirmed;
use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected VnpayService $vnpay,
        protected PaymentRepositoryInterface $payments,
        protected BookingRepositoryInterface $bookings,
        protected RefundService $refunds,
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
            $this->payments->update($payment, ['status' => PaymentStatus::COMPLETED->value]);
            $booking = $payment->booking;
            if ($booking && $booking->status !== BookingStatus::CONFIRMED->value) {
                $this->bookings->update($booking, ['status' => BookingStatus::CONFIRMED->value]);
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
        $this->payments->update($payment, ['status' => PaymentStatus::FAILED->value, 'payload' => $payload]);
    }

    /**
     * Record a refund. Flag off keeps the legacy local accounting; flag on
     * creates a VNPay obligation handled by RefundService/ProcessVnpayRefund.
     *
     * @param  float  $amount  Amount to refund in booking currency (VND)
     */
    public function refund(Payment $payment, float $amount, ?string $reason = null, ?string $idempotencyKey = null): void
    {
        $this->refunds->request($payment, $amount, $reason, $idempotencyKey);
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
