<?php

namespace App\Repositories\Eloquent;

use App\Models\OutboxEvent;
use App\Repositories\Contracts\OutboxEventRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class EloquentOutboxEventRepository implements OutboxEventRepositoryInterface
{
    public function createIfAbsent(array $attributes): OutboxEvent
    {
        $eventId = (string) ($attributes['event_id'] ?? '');

        $existing = OutboxEvent::where('event_id', $eventId)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return OutboxEvent::create($attributes);
        } catch (UniqueConstraintViolationException $e) {
            $existing = OutboxEvent::where('event_id', $eventId)->first();
            if ($existing !== null) {
                return $existing;
            }

            throw $e;
        }
    }

    public function findById(int $id): ?OutboxEvent
    {
        return OutboxEvent::find($id);
    }

    public function claimBatch(int $limit = 100, int $leaseSeconds = 90): Collection
    {
        $limit = max(1, $limit);
        $leaseSeconds = max(1, $leaseSeconds);

        return DB::transaction(function () use ($limit, $leaseSeconds): Collection {
            $now = now();

            $events = OutboxEvent::query()
                ->whereNull('published_at')
                ->where('attempts', '<', (int) Config::get('outbox.max_attempts', 3))
                ->where(function ($query) use ($now): void {
                    $query->whereNull('available_at')
                        ->orWhere('available_at', '<=', $now);
                })
                ->where(function ($query) use ($now): void {
                    $query->whereNull('lease_expires_at')
                        ->orWhere('lease_expires_at', '<=', $now);
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $leaseExpiresAt = $now->copy()->addSeconds($leaseSeconds);

            foreach ($events as $event) {
                $event->update([
                    'locked_at' => $now,
                    'lease_expires_at' => $leaseExpiresAt,
                ]);
            }

            return $events->fresh() ?? $events;
        });
    }

    public function markPublished(OutboxEvent $event, ?\DateTimeInterface $publishedAt = null): OutboxEvent
    {
        $event->update([
            'published_at' => $publishedAt ?? now(),
            'locked_at' => null,
            'lease_expires_at' => null,
            'last_error' => null,
        ]);

        return $event->fresh() ?? $event;
    }

    public function scheduleRetry(OutboxEvent $event, \DateTimeInterface $availableAt, ?string $lastError = null): OutboxEvent
    {
        $event->update([
            'available_at' => $availableAt,
            'attempts' => $event->attempts + 1,
            'locked_at' => null,
            'lease_expires_at' => null,
            'last_error' => $lastError,
        ]);

        return $event->fresh() ?? $event;
    }

    public function markFailed(OutboxEvent $event, ?string $lastError = null): OutboxEvent
    {
        $event->update([
            'attempts' => $event->attempts + 1,
            'locked_at' => null,
            'lease_expires_at' => null,
            'last_error' => $lastError,
        ]);

        return $event->fresh() ?? $event;
    }

    public function releaseStaleLeases(?\DateTimeInterface $now = null): int
    {
        $now = $now ?? now();

        return OutboxEvent::query()
            ->whereNull('published_at')
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', $now)
            ->update([
                'locked_at' => null,
                'lease_expires_at' => null,
            ]);
    }

    public function oldestPendingAvailableAt(): ?\DateTimeInterface
    {
        $event = OutboxEvent::query()
            ->whereNull('published_at')
            ->where('attempts', '<', (int) Config::get('outbox.max_attempts', 3))
            ->orderBy('available_at')
            ->first(['available_at']);

        return $event?->available_at;
    }

    public function failedCount(): int
    {
        return OutboxEvent::query()
            ->whereNull('published_at')
            ->where('attempts', '>=', (int) Config::get('outbox.max_attempts', 3))
            ->count();
    }
}
