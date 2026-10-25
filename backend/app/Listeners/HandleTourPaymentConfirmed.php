<?php

namespace App\Listeners;

use App\Enums\TourBookingStatus;
use App\Events\TourPaymentConfirmed;
use App\Models\EventConsumptionReceipt;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleTourPaymentConfirmed implements ShouldQueue
{
    public function __construct(
        protected TourPaymentRepositoryInterface $payments,
        protected TourBookingRepositoryInterface $bookings,
    ) {}

    public function handle(TourPaymentConfirmed $event): void
    {
        $consumer = self::class;

        if (EventConsumptionReceipt::where('consumer', $consumer)
            ->where('event_id', $event->eventId)
            ->exists()) {
            return;
        }

        try {
            DB::transaction(function () use ($event, $consumer): void {
                EventConsumptionReceipt::create([
                    'consumer' => $consumer,
                    'event_id' => $event->eventId,
                    'processed_at' => now(),
                ]);

                $payment = $this->payments->findById($event->paymentId);

                if ($payment === null) {
                    throw new \RuntimeException('Tour payment not found for confirmed event: '.$event->paymentId);
                }

                if ($payment->tour_booking_id !== null) {
                    $booking = $this->bookings->findById((int) $payment->tour_booking_id);

                    if ($booking !== null && $booking->status !== TourBookingStatus::CONFIRMED->value) {
                        $this->bookings->update($booking, ['status' => TourBookingStatus::CONFIRMED->value]);
                    }
                }
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'UNIQUE') || str_contains($exception->getMessage(), 'unique')) {
                return;
            }

            throw $exception;
        }
    }

    public function failed(TourPaymentConfirmed $event, \Throwable $exception): void
    {
        Log::error('Tour payment confirmed consumer failed', [
            'event_id' => $event->eventId,
            'payment_id' => $event->paymentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
