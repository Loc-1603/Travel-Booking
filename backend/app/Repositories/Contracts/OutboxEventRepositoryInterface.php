<?php

namespace App\Repositories\Contracts;

use App\Models\OutboxEvent;
use Illuminate\Support\Collection;

interface OutboxEventRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createIfAbsent(array $attributes): OutboxEvent;

    public function findById(int $id): ?OutboxEvent;

    /**
     * @return Collection<int, OutboxEvent>
     */
    public function claimBatch(int $limit = 100, int $leaseSeconds = 90): Collection;

    public function markPublished(OutboxEvent $event, ?\DateTimeInterface $publishedAt = null): OutboxEvent;

    public function scheduleRetry(OutboxEvent $event, \DateTimeInterface $availableAt, ?string $lastError = null): OutboxEvent;

    public function markFailed(OutboxEvent $event, ?string $lastError = null): OutboxEvent;

    public function releaseStaleLeases(?\DateTimeInterface $now = null): int;

    public function oldestPendingAvailableAt(): ?\DateTimeInterface;

    public function failedCount(): int;
}
