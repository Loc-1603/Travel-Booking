<?php

namespace App\Repositories\Eloquent;

use App\Enums\RefundStatus;
use App\Models\PaymentRefund;
use App\Repositories\Contracts\PaymentRefundRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EloquentPaymentRefundRepository implements PaymentRefundRepositoryInterface
{
    public function createIfAbsent(array $attributes): PaymentRefund
    {
        $paymentType = (string) ($attributes['payment_type'] ?? '');
        $paymentId = (int) ($attributes['payment_id'] ?? 0);
        $idempotencyKey = (string) ($attributes['idempotency_key'] ?? '');

        if ($paymentType === '' || $paymentId <= 0 || $idempotencyKey === '') {
            throw new \InvalidArgumentException('payment_type, payment_id and idempotency_key are required.');
        }

        $existing = $this->findByIdempotencyKey($paymentType, $paymentId, $idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }

        $attributes = array_merge([
            'uuid' => (string) Str::uuid(),
            'provider' => 'vnpay',
            'currency' => 'VND',
            'status' => RefundStatus::PENDING->value,
            'attempts' => 0,
        ], $attributes);

        if (empty($attributes['request_id'])) {
            $attributes['request_id'] = (string) Str::uuid()->getHex();
        }

        try {
            return PaymentRefund::create($attributes);
        } catch (UniqueConstraintViolationException $e) {
            $existing = $this->findByIdempotencyKey($paymentType, $paymentId, $idempotencyKey);

            if ($existing !== null) {
                return $existing;
            }

            throw $e;
        }
    }

    public function findById(int $id): ?PaymentRefund
    {
        return PaymentRefund::find($id);
    }

    public function findByIdForUpdate(int $id): ?PaymentRefund
    {
        return PaymentRefund::whereKey($id)->lockForUpdate()->first();
    }

    public function findByUuid(string $uuid): ?PaymentRefund
    {
        return PaymentRefund::where('uuid', $uuid)->first();
    }

    public function findByIdempotencyKey(string $paymentType, int $paymentId, string $idempotencyKey): ?PaymentRefund
    {
        return PaymentRefund::query()
            ->where('payment_type', $paymentType)
            ->where('payment_id', $paymentId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function findActiveForPayment(string $paymentType, int $paymentId): ?PaymentRefund
    {
        return PaymentRefund::query()
            ->where('payment_type', $paymentType)
            ->where('payment_id', $paymentId)
            ->whereIn('status', RefundStatus::activeValues())
            ->orderByDesc('id')
            ->first();
    }

    public function findLatestForPayment(string $paymentType, int $paymentId): ?PaymentRefund
    {
        return PaymentRefund::query()
            ->where('payment_type', $paymentType)
            ->where('payment_id', $paymentId)
            ->orderByDesc('id')
            ->first();
    }

    public function sumReserved(string $paymentType, int $paymentId): float
    {
        return (float) PaymentRefund::query()
            ->where('payment_type', $paymentType)
            ->where('payment_id', $paymentId)
            ->where('status', RefundStatus::PENDING->value)
            ->sum('amount');
    }

    public function claimBatch(int $limit = 100, int $leaseSeconds = 90): Collection
    {
        $limit = max(1, $limit);
        $leaseSeconds = max(1, $leaseSeconds);
        $maxAttempts = (int) Config::get('services.vnpay.refund_max_attempts', 5);

        return DB::transaction(function () use ($limit, $leaseSeconds, $maxAttempts): Collection {
            $now = now();

            $refunds = PaymentRefund::query()
                ->where(function ($query): void {
                    $query->where('status', RefundStatus::PENDING->value)
                        ->orWhere(function ($query): void {
                            $query->where('status', RefundStatus::PROCESSING->value)
                                ->where(function ($query): void {
                                    $query->whereNotNull('last_error')
                                        ->orWhereNull('vnp_response_code');
                                });
                        });
                })
                ->where('attempts', '<', $maxAttempts)
                ->where(function ($query) use ($now): void {
                    $query->whereNull('next_retry_at')
                        ->orWhere('next_retry_at', '<=', $now);
                })
                ->where(function ($query) use ($now): void {
                    $query->whereNull('lease_expires_at')
                        ->orWhere('lease_expires_at', '<=', $now);
                })
                ->orderBy('next_retry_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $leaseExpiresAt = $now->copy()->addSeconds($leaseSeconds);

            foreach ($refunds as $refund) {
                $refund->update([
                    'locked_at' => $now,
                    'lease_expires_at' => $leaseExpiresAt,
                ]);
            }

            return $refunds->fresh() ?? $refunds;
        });
    }

    public function markProcessing(PaymentRefund $refund, ?\DateTimeInterface $leaseExpiresAt = null): PaymentRefund
    {
        $refund->update([
            'status' => RefundStatus::PROCESSING->value,
            'locked_at' => now(),
            'lease_expires_at' => $leaseExpiresAt ?? now()->addSeconds(90),
            'next_retry_at' => null,
        ]);

        return $refund->fresh() ?? $refund;
    }

    public function markAcceptedProcessing(PaymentRefund $refund, array $attributes = []): PaymentRefund
    {
        $refund->update(array_merge($attributes, [
            'status' => RefundStatus::PROCESSING->value,
            'attempts' => (int) $refund->attempts + 1,
            'locked_at' => null,
            'lease_expires_at' => null,
            'next_retry_at' => null,
            'last_error' => null,
        ]));

        return $refund->fresh() ?? $refund;
    }

    public function markPendingForReplay(PaymentRefund $refund): PaymentRefund
    {
        $refund->update([
            'status' => RefundStatus::PENDING->value,
            'attempts' => 0,
            'next_retry_at' => null,
            'locked_at' => null,
            'lease_expires_at' => null,
            'last_error' => null,
            'vnp_response_code' => null,
            'vnp_transaction_status' => null,
            'vnp_refund_transaction_no' => null,
            'vnp_pay_date' => null,
            'provider_response' => null,
        ]);

        return $refund->fresh() ?? $refund;
    }

    public function markSucceeded(PaymentRefund $refund, array $attributes = []): PaymentRefund
    {
        $refund->update(array_merge($attributes, [
            'status' => RefundStatus::SUCCEEDED->value,
            'locked_at' => null,
            'lease_expires_at' => null,
            'next_retry_at' => null,
            'last_error' => null,
        ]));

        return $refund->fresh() ?? $refund;
    }

    public function markFailed(PaymentRefund $refund, ?string $lastError = null, array $attributes = []): PaymentRefund
    {
        return $this->finishWithStatus($refund, RefundStatus::FAILED, $lastError, $attributes);
    }

    public function markRejected(PaymentRefund $refund, ?string $lastError = null, array $attributes = []): PaymentRefund
    {
        return $this->finishWithStatus($refund, RefundStatus::REJECTED, $lastError, $attributes);
    }

    public function markNeedsReview(PaymentRefund $refund, ?string $lastError = null, array $attributes = []): PaymentRefund
    {
        $refund->update(array_merge($attributes, [
            'status' => RefundStatus::NEEDS_REVIEW->value,
            'locked_at' => null,
            'lease_expires_at' => null,
            'next_retry_at' => null,
            'last_error' => $lastError,
        ]));

        return $refund->fresh() ?? $refund;
    }

    public function scheduleRetry(PaymentRefund $refund, \DateTimeInterface $availableAt, ?string $lastError = null): PaymentRefund
    {
        $refund->update([
            'next_retry_at' => $availableAt,
            'attempts' => (int) $refund->attempts + 1,
            'locked_at' => null,
            'lease_expires_at' => null,
            'last_error' => $lastError,
        ]);

        return $refund->fresh() ?? $refund;
    }

    public function releaseStaleLeases(?\DateTimeInterface $now = null): int
    {
        $now = $now ?? now();

        return PaymentRefund::query()
            ->whereIn('status', [RefundStatus::PENDING->value, RefundStatus::PROCESSING->value])
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', $now)
            ->update([
                'locked_at' => null,
                'lease_expires_at' => null,
            ]);
    }

    public function countByStatuses(array $statuses): array
    {
        if ($statuses === []) {
            return [];
        }

        return PaymentRefund::query()
            ->whereIn('status', $statuses)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finishWithStatus(PaymentRefund $refund, RefundStatus $status, ?string $lastError, array $attributes): PaymentRefund
    {
        $refund->update(array_merge($attributes, [
            'status' => $status->value,
            'attempts' => (int) $refund->attempts + 1,
            'locked_at' => null,
            'lease_expires_at' => null,
            'next_retry_at' => null,
            'last_error' => $lastError,
        ]));

        return $refund->fresh() ?? $refund;
    }
}
