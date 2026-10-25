<?php

namespace App\Events;

use App\Models\TourPayment;
use Illuminate\Foundation\Events\Dispatchable;

class TourPaymentConfirmed
{
    use Dispatchable;

    public string $eventId;

    public string $event_id;

    public int $paymentId;

    public int $payment_id;

    public ?int $tourBookingId;

    public ?int $tour_booking_id;

    public string $occurredAt;

    public string $occurred_at;

    public int $eventVersion;

    public int $event_version;

    public function __construct(
        string|TourPayment|null $eventId = null,
        ?int $paymentId = null,
        ?int $tourBookingId = null,
        ?string $occurredAt = null,
        int $eventVersion = 1,
    ) {
        if ($eventId instanceof TourPayment) {
            $payment = $eventId;
            $eventId = 'tour-payment-confirmed:'.$payment->id;
            $paymentId = $paymentId ?? (int) $payment->id;
            $tourBookingId = $tourBookingId ?? ($payment->tour_booking_id !== null ? (int) $payment->tour_booking_id : null);
            $occurredAt = $occurredAt ?? now()->toIso8601String();
        }

        $this->eventId = $this->event_id = $eventId ?? ($paymentId !== null ? 'tour-payment-confirmed:'.$paymentId : '');
        $this->paymentId = $this->payment_id = $paymentId ?? 0;
        $this->tourBookingId = $this->tour_booking_id = $tourBookingId;
        $this->occurredAt = $this->occurred_at = $occurredAt ?? now()->toIso8601String();
        $this->eventVersion = $this->event_version = $eventVersion;
    }
}
