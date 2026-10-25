<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Events\TourPaymentConfirmed;
use App\Repositories\Contracts\OutboxEventRepositoryInterface;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TourPaymentService
{
    public function __construct(
        protected TourVnpayAdapter $vnpay,
        protected TourPaymentRepositoryInterface $payments,
        protected TourBookingRepositoryInterface $bookings,
        protected OutboxEventRepositoryInterface $outbox,
        protected ?RefundService $refunds = null,
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
        DB::transaction(function () use ($payment): void {
            $lockedPayment = $this->payments->findByIdForUpdate((int) $payment->id);

            if ($lockedPayment === null) {
                Log::warning('TourPaymentService::confirmPayment payment not found', ['payment_id' => $payment->id]);

                return;
            }

            if ($lockedPayment->status === PaymentStatus::COMPLETED->value) {
                return;
            }

            $lockedPayment = $this->payments->update($lockedPayment, [
                'status' => PaymentStatus::COMPLETED->value,
            ]);

            $booking = $lockedPayment->tour_booking_id !== null
                ? $this->bookings->findById((int) $lockedPayment->tour_booking_id)
                : null;

            if ($booking !== null && $booking->status !== TourBookingStatus::CONFIRMED->value) {
                $this->bookings->update($booking, ['status' => TourBookingStatus::CONFIRMED->value]);
            } elseif ($lockedPayment->tour_booking_id !== null && $booking === null) {
                Log::warning('TourPaymentService::confirmPayment booking not found', [
                    'payment_id' => $lockedPayment->id,
                    'tour_booking_id' => $lockedPayment->tour_booking_id,
                ]);
            }

            $occurredAt = now();

            $this->outbox->createIfAbsent([
                'event_id' => 'tour-payment-confirmed:'.$lockedPayment->id,
                'event_type' => TourPaymentConfirmed::class,
                'event_version' => 1,
                'payload' => [
                    'payment_id' => $lockedPayment->id,
                    'tour_booking_id' => $lockedPayment->tour_booking_id,
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'event_version' => 1,
                ],
                'available_at' => $occurredAt,
            ]);
        });
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
        $this->refunds()->request($payment, $amount, $reason);
    }

    private function refunds(): RefundService
    {
        return $this->refunds ??= app(RefundService::class);
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
