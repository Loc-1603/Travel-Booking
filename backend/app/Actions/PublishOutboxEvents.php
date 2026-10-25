<?php

namespace App\Actions;

use App\Events\TourPaymentConfirmed;
use App\Models\OutboxEvent;
use App\Repositories\Contracts\OutboxEventRepositoryInterface;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use Illuminate\Support\Facades\Log;

class PublishOutboxEvents
{
    public function __construct(
        protected OutboxEventRepositoryInterface $outbox,
        protected TourPaymentRepositoryInterface $payments,
    ) {}

    /**
     * @return array{claimed: int, published: int, retried: int, failed: int}
     */
    public function handle(int $limit = 100, int $leaseSeconds = 90): array
    {
        $limit = max(1, $limit);
        $leaseSeconds = max(1, $leaseSeconds);

        $this->outbox->releaseStaleLeases();

        $events = $this->outbox->claimBatch($limit, $leaseSeconds);

        $published = 0;
        $retried = 0;
        $failed = 0;

        foreach ($events as $event) {
            try {
                $this->publish($event);
                $published++;
            } catch (\Throwable $exception) {
                $error = $this->redact($exception);

                if ($event->attempts + 1 >= $this->maxAttempts()) {
                    $this->outbox->markFailed($event, $error);
                    $failed++;

                    Log::error('Outbox event failed permanently', [
                        'event_id' => $event->event_id,
                        'event_type' => $event->event_type,
                        'payment_id' => $event->payload['payment_id'] ?? null,
                        'attempts' => $event->attempts + 1,
                        'error' => $error,
                    ]);
                } else {
                    $delay = $this->backoff()[$event->attempts] ?? $this->backoff()[array_key_last($this->backoff())];
                    $this->outbox->scheduleRetry($event, now()->addSeconds($delay), $error);
                    $retried++;

                    Log::warning('Outbox event scheduled for retry', [
                        'event_id' => $event->event_id,
                        'event_type' => $event->event_type,
                        'payment_id' => $event->payload['payment_id'] ?? null,
                        'attempts' => $event->attempts + 1,
                        'available_at' => now()->addSeconds($delay)->toIso8601String(),
                        'error' => $error,
                    ]);
                }
            }
        }

        $oldestPending = $this->outbox->oldestPendingAvailableAt();
        $pendingAgeSeconds = $oldestPending !== null
            ? (int) now()->diffInSeconds($oldestPending)
            : 0;

        Log::info('Outbox publish run completed', [
            'claimed' => $events->count(),
            'published' => $published,
            'retried' => $retried,
            'failed' => $failed,
            'pending_age_seconds' => $pendingAgeSeconds,
            'dead_letter_count' => $this->outbox->failedCount(),
        ]);

        return [
            'claimed' => $events->count(),
            'published' => $published,
            'retried' => $retried,
            'failed' => $failed,
        ];
    }

    public function replay(string $eventId): bool
    {
        $event = \App\Models\OutboxEvent::where('event_id', $eventId)->first();

        if ($event === null) {
            return false;
        }

        $event->update([
            'available_at' => now(),
            'published_at' => null,
            'attempts' => 0,
            'locked_at' => null,
            'lease_expires_at' => null,
            'last_error' => null,
        ]);

        Log::info('Outbox event replay requested', [
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'payment_id' => $event->payload['payment_id'] ?? null,
        ]);

        return true;
    }

    protected function publish(OutboxEvent $event): void
    {
        if ($event->event_type !== TourPaymentConfirmed::class) {
            throw new \RuntimeException('Unsupported outbox event type: '.$event->event_type);
        }

        $paymentId = (int) ($event->payload['payment_id'] ?? 0);
        $payment = $this->payments->findById($paymentId);

        if ($payment === null) {
            throw new \RuntimeException('Tour payment not found for outbox event');
        }

        $tourBookingId = array_key_exists('tour_booking_id', $event->payload ?? [])
            ? ($event->payload['tour_booking_id'] !== null ? (int) $event->payload['tour_booking_id'] : null)
            : ($payment->tour_booking_id !== null ? (int) $payment->tour_booking_id : null);

        event(new TourPaymentConfirmed(
            eventId: (string) $event->event_id,
            paymentId: $paymentId,
            tourBookingId: $tourBookingId,
            occurredAt: (string) ($event->payload['occurred_at'] ?? now()->toIso8601String()),
            eventVersion: (int) ($event->payload['event_version'] ?? $event->event_version ?? 1),
        ));

        $this->outbox->markPublished($event);
    }

    protected function maxAttempts(): int
    {
        return (int) config('outbox.max_attempts', 3);
    }

    /**
     * @return array<int, int>
     */
    protected function backoff(): array
    {
        $backoff = config('outbox.backoff', [10, 30, 90]);

        return is_array($backoff) && $backoff !== [] ? array_values($backoff) : [10, 30, 90];
    }

    protected function redact(\Throwable $exception): string
    {
        $message = str_replace(["\r", "\n"], ' ', $exception->getMessage());

        return mb_substr($message, 0, 500);
    }
}
