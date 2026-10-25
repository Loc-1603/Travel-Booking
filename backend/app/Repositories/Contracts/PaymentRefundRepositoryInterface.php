<?php

namespace App\Repositories\Contracts;

use App\Models\PaymentRefund;
use Illuminate\Support\Collection;

interface PaymentRefundRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createIfAbsent(array $attributes): PaymentRefund;

    public function findById(int $id): ?PaymentRefund;

    public function findByIdForUpdate(int $id): ?PaymentRefund;

    public function findByUuid(string $uuid): ?PaymentRefund;

    public function findByIdempotencyKey(string $paymentType, int $paymentId, string $idempotencyKey): ?PaymentRefund;

    public function findActiveForPayment(string $paymentType, int $paymentId): ?PaymentRefund;

    public function findLatestForPayment(string $paymentType, int $paymentId): ?PaymentRefund;

    public function sumReserved(string $paymentType, int $paymentId): float;

    /**
     * @return Collection<int, PaymentRefund>
     */
    public function claimBatch(int $limit = 100, int $leaseSeconds = 90): Collection;

    public function markProcessing(PaymentRefund $refund, ?\DateTimeInterface $leaseExpiresAt = null): PaymentRefund;

    /**
     * Provider accepted the refund but it is not settled yet (vnp_TransactionStatus 01/05/06 or replayed request).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function markAcceptedProcessing(PaymentRefund $refund, array $attributes = []): PaymentRefund;

    /**
     * Reset a refund row so the same obligation/request id can be replayed.
     */
    public function markPendingForReplay(PaymentRefund $refund): PaymentRefund;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function markSucceeded(PaymentRefund $refund, array $attributes = []): PaymentRefund;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function markFailed(PaymentRefund $refund, ?string $lastError = null, array $attributes = []): PaymentRefund;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function markRejected(PaymentRefund $refund, ?string $lastError = null, array $attributes = []): PaymentRefund;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function markNeedsReview(PaymentRefund $refund, ?string $lastError = null, array $attributes = []): PaymentRefund;

    public function scheduleRetry(PaymentRefund $refund, \DateTimeInterface $availableAt, ?string $lastError = null): PaymentRefund;

    public function releaseStaleLeases(?\DateTimeInterface $now = null): int;

    /**
     * @param  array<int, string>  $statuses
     * @return array<string, int>
     */
    public function countByStatuses(array $statuses): array;
}
