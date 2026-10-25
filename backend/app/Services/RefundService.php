<?php

namespace App\Services;

use App\DTOs\VnpayRefundData;
use App\DTOs\VnpayRefundResult;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Jobs\ProcessVnpayRefund;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\TourPayment;
use App\Repositories\Contracts\PaymentRefundRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Owns refund obligations for hotel + tour payments.
 *
 * Flag off (or a non-VNPay payment): local accounting only, exactly like the
 * legacy refund path. Flag on: persist a pending obligation in the same
 * transaction as the payment lock, then let ProcessVnpayRefund contact VNPay.
 */
class RefundService
{
    public function __construct(
        protected PaymentRepositoryInterface $hotelPayments,
        protected TourPaymentRepositoryInterface $tourPayments,
        protected PaymentRefundRepositoryInterface $refunds,
        protected VnpayRefundClient $client,
    ) {}

    public function request(
        Payment|TourPayment $payment,
        float $amount,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        ?int $requestedBy = null,
    ): ?PaymentRefund {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $reason = $reason ?? 'refund';
        $idempotencyKey = $idempotencyKey ?: $this->defaultIdempotencyKey($payment, $amount);

        if (! $this->client->enabled() || ! $this->usesVnpay($payment)) {
            $this->recordLocally($payment, $amount, $reason);

            return null;
        }

        $refund = DB::transaction(function () use ($payment, $amount, $reason, $idempotencyKey, $requestedBy): ?PaymentRefund {
            [$type, $locked] = $this->lockPayment($payment);

            $existing = $this->refunds->findByIdempotencyKey($type, (int) $locked->id, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            $latest = $this->refunds->findLatestForPayment($type, (int) $locked->id);
            if ($latest !== null) {
                Log::warning('RefundService: payment already has a refund obligation', [
                    'payment_type' => $type,
                    'payment_id' => $locked->id,
                    'refund_id' => $latest->id,
                    'refund_status' => $latest->status,
                ]);

                return $latest;
            }

            if (! in_array($locked->status, [PaymentStatus::COMPLETED->value, PaymentStatus::REFUNDED->value], true)) {
                throw new InvalidArgumentException('Only completed payments can be refunded.');
            }

            $available = round(
                (float) $locked->amount
                    - (float) ($locked->refunded_amount ?? 0)
                    - $this->refunds->sumReserved($type, (int) $locked->id),
                2,
            );

            if ($amount > $available + 0.001) {
                throw new InvalidArgumentException("Refund amount must be between 0 and {$available}.");
            }

            $txnRef = (string) ($locked->external_id ?? '');
            if ($txnRef === '') {
                throw new InvalidArgumentException('Payment is missing its VNPay transaction reference.');
            }

            $isFull = (float) ($locked->refunded_amount ?? 0) <= 0
                && abs($amount - (float) $locked->amount) < 0.01;

            return $this->refunds->createIfAbsent([
                'payment_type' => $type,
                'payment_id' => (int) $locked->id,
                'idempotency_key' => $idempotencyKey,
                'txn_ref' => $txnRef,
                'amount' => $amount,
                'type' => ($isFull ? RefundType::FULL : RefundType::PARTIAL)->value,
                'reason' => Str::limit($reason, 255),
                'requested_by' => $requestedBy,
            ]);
        });

        if ($refund !== null && $refund->wasRecentlyCreated) {
            Log::info('refund.obligation_created', [
                'refund_id' => $refund->id,
                'payment_type' => $refund->payment_type,
                'payment_id' => $refund->payment_id,
                'request_id' => $refund->request_id,
                'amount' => (float) $refund->amount,
                'type' => $refund->type,
            ]);
        }

        if ($refund !== null && $refund->status === RefundStatus::PENDING->value) {
            ProcessVnpayRefund::dispatch((int) $refund->id)->afterCommit();
        }

        return $refund;
    }

    /**
     * Execute one provider attempt for a persisted refund row (job/command entry point).
     */
    public function process(int $refundId, ?DateTimeInterface $claimedUntil = null): void
    {
        if (! $this->client->enabled()) {
            Log::info('RefundService: refunds disabled, leaving obligation pending', [
                'refund_id' => $refundId,
            ]);

            return;
        }

        $refund = $this->claim($refundId, $claimedUntil);
        if ($refund === null) {
            return;
        }

        $payment = $this->findPayment($refund);
        if ($payment === null) {
            $this->refunds->markNeedsReview($refund, 'Payment not found for refund obligation.');
            $this->logOutcome($refund);

            return;
        }

        $data = $this->buildProviderData($refund, $payment);
        if ($data instanceof VnpayRefundResult) {
            $this->logOutcome($refund);

            return;
        }

        try {
            $result = $this->client->refund($data);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->refunds->markNeedsReview($refund, Str::limit($e->getMessage(), 500));
            $this->logOutcome($refund);

            return;
        } catch (Throwable $e) {
            $this->refunds->markNeedsReview(
                $refund,
                Str::limit('Refund request failed: '.$e->getMessage(), 500),
            );
            $this->logOutcome($refund);

            return;
        }

        $this->applyResult($refund, $result);
        $this->logOutcome($refund);
    }

    /**
     * Close a failed/rejected/needs_review obligation after an off-platform refund.
     *
     * Credits the payment once, appends a manual audit entry and marks the
     * obligation succeeded. Pending/processing refunds are still in flight and
     * must not be settled manually.
     */
    public function settleManually(PaymentRefund $refund, string $note, ?string $operator = null): PaymentRefund
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('A manual settlement note is required.');
        }

        return DB::transaction(function () use ($refund, $note, $operator): PaymentRefund {
            $locked = $this->refunds->findByIdForUpdate((int) $refund->id);
            if ($locked === null) {
                throw new InvalidArgumentException('Refund not found.');
            }

            if (! in_array($locked->status, [
                RefundStatus::FAILED->value,
                RefundStatus::REJECTED->value,
                RefundStatus::NEEDS_REVIEW->value,
            ], true)) {
                throw new InvalidArgumentException('Only failed, rejected or needs_review refunds can be settled manually.');
            }

            $payment = $this->lockPaymentForRefund($locked);
            if ($payment === null) {
                throw new InvalidArgumentException('Payment not found for refund obligation.');
            }

            if (! in_array($payment->status, [PaymentStatus::COMPLETED->value, PaymentStatus::REFUNDED->value], true)) {
                throw new InvalidArgumentException('Only completed payments can be settled manually.');
            }

            $payload = $payment->payload ?? [];
            $alreadyCredited = collect($payload['refunds'] ?? [])
                ->contains(fn (mixed $entry): bool => is_array($entry)
                    && (int) ($entry['refund_id'] ?? 0) === (int) $locked->id);

            if (! $alreadyCredited) {
                $newRefunded = round((float) ($payment->refunded_amount ?? 0) + (float) $locked->amount, 2);
                if ($newRefunded > (float) $payment->amount + 0.001) {
                    throw new InvalidArgumentException('Refund would exceed the original payment amount.');
                }

                $payload['refunds'][] = [
                    'amount' => (float) $locked->amount,
                    'reason' => $locked->reason,
                    'refunded_at' => now()->toIso8601String(),
                    'refund_id' => $locked->id,
                    'request_id' => $locked->request_id,
                    'status' => RefundStatus::SUCCEEDED->value,
                    'manual' => true,
                    'note' => Str::limit($note, 255),
                    'operator' => $operator,
                ];

                $paymentAttributes = [
                    'refunded_amount' => $newRefunded,
                    'status' => $newRefunded + 0.001 >= (float) $payment->amount
                        ? PaymentStatus::REFUNDED->value
                        : PaymentStatus::COMPLETED->value,
                    'payload' => $payload,
                ];

                if ($payment instanceof Payment) {
                    $this->hotelPayments->update($payment, $paymentAttributes);
                } else {
                    $this->tourPayments->update($payment, $paymentAttributes);
                }
            }

            $response = $locked->provider_response ?? [];
            $response['manual_settlement'] = [
                'note' => Str::limit($note, 500),
                'operator' => $operator,
                'settled_at' => now()->toIso8601String(),
            ];

            $settled = $this->refunds->markSucceeded($locked, ['provider_response' => $response]);

            Log::info('refund.manual_settled', [
                'refund_id' => $settled->id,
                'payment_type' => $settled->payment_type,
                'payment_id' => $settled->payment_id,
                'request_id' => $settled->request_id,
                'amount' => (float) $settled->amount,
                'operator' => $operator,
                'note' => Str::limit($note, 200),
                'already_credited' => $alreadyCredited,
            ]);

            return $settled;
        });
    }

    /**
     * True when refunded_amount + pending reservations cover the payment amount.
     */
    public function fullyReserved(Payment|TourPayment $payment): bool
    {
        $type = $payment instanceof Payment ? PaymentRefund::TYPE_HOTEL : PaymentRefund::TYPE_TOUR;
        $covered = (float) ($payment->refunded_amount ?? 0)
            + $this->refunds->sumReserved($type, (int) $payment->id);

        return $covered + 0.001 >= (float) $payment->amount;
    }

    public function defaultIdempotencyKey(Payment|TourPayment $payment, float $amount): string
    {
        $type = $payment instanceof Payment ? PaymentRefund::TYPE_HOTEL : PaymentRefund::TYPE_TOUR;

        return sprintf('refund:%s:%d:%.2f', $type, (int) $payment->id, round($amount, 2));
    }

    private function claim(int $refundId, ?DateTimeInterface $claimedUntil): ?PaymentRefund
    {
        return DB::transaction(function () use ($refundId, $claimedUntil): ?PaymentRefund {
            $refund = $this->refunds->findByIdForUpdate($refundId);
            if ($refund === null) {
                return null;
            }

            if (in_array($refund->status, [
                RefundStatus::SUCCEEDED->value,
                RefundStatus::FAILED->value,
                RefundStatus::REJECTED->value,
                RefundStatus::NEEDS_REVIEW->value,
            ], true)) {
                return null;
            }

            if ($this->isAcceptedProcessing($refund)) {
                return null;
            }

            $lease = $refund->lease_expires_at;
            if ($lease !== null && $lease->isFuture()) {
                if ($claimedUntil === null || abs($lease->timestamp - $claimedUntil->timestamp) > 1) {
                    return null;
                }
            }

            $maxAttempts = max(1, (int) config('services.vnpay.refund_max_attempts', 5));
            if ((int) $refund->attempts >= $maxAttempts) {
                $this->refunds->markNeedsReview($refund, 'Retry limit reached.');

                return null;
            }

            return $this->refunds->markProcessing($refund, now()->addSeconds(90));
        });
    }

    private function isAcceptedProcessing(PaymentRefund $refund): bool
    {
        return $refund->status === RefundStatus::PROCESSING->value
            && $refund->last_error === null
            && in_array((string) $refund->vnp_response_code, ['00', '94'], true);
    }

    private function findPayment(PaymentRefund $refund): Payment|TourPayment|null
    {
        return $refund->payment_type === PaymentRefund::TYPE_TOUR
            ? $this->tourPayments->findById((int) $refund->payment_id)
            : $this->hotelPayments->findById((int) $refund->payment_id);
    }

    /**
     * @return Payment|TourPayment
     */
    private function lockPayment(Payment|TourPayment $payment): array
    {
        if ($payment instanceof Payment) {
            $locked = $this->hotelPayments->findByIdForUpdate((int) $payment->id);
            $type = PaymentRefund::TYPE_HOTEL;
        } else {
            $locked = $this->tourPayments->findByIdForUpdate((int) $payment->id);
            $type = PaymentRefund::TYPE_TOUR;
        }

        if ($locked === null) {
            throw new InvalidArgumentException('Payment not found.');
        }

        return [$type, $locked];
    }

    private function usesVnpay(Payment|TourPayment $payment): bool
    {
        return $payment->provider === 'vnpay' && (string) ($payment->external_id ?? '') !== '';
    }

    private function recordLocally(Payment|TourPayment $payment, float $amount, string $reason): void
    {
        DB::transaction(function () use ($payment, $amount, $reason): void {
            [, $locked] = $this->lockPayment($payment);

            $maxRefundable = round((float) $locked->amount - (float) ($locked->refunded_amount ?? 0), 2);
            if ($amount <= 0 || $amount > $maxRefundable + 0.001) {
                throw new InvalidArgumentException("Refund amount must be between 0 and {$maxRefundable}.");
            }

            $newRefunded = round((float) ($locked->refunded_amount ?? 0) + $amount, 2);
            $status = $newRefunded + 0.001 >= (float) $locked->amount
                ? PaymentStatus::REFUNDED->value
                : PaymentStatus::COMPLETED->value;

            $payload = $locked->payload ?? [];
            $payload['refunds'][] = [
                'amount' => $amount,
                'reason' => $reason,
                'refunded_at' => now()->toIso8601String(),
            ];

            $attributes = [
                'refunded_amount' => $newRefunded,
                'status' => $status,
                'payload' => $payload,
            ];

            if ($locked instanceof Payment) {
                $this->hotelPayments->update($locked, $attributes);
            } else {
                $this->tourPayments->update($locked, $attributes);
            }
        });
    }

    private function buildProviderData(PaymentRefund $refund, Payment|TourPayment $payment): VnpayRefundData|VnpayRefundResult
    {
        $txnRef = (string) ($payment->external_id ?? '');
        if ($txnRef === '') {
            $this->refunds->markNeedsReview($refund, 'Missing VNPay transaction reference.');

            return new VnpayRefundResult(
                verified: false,
                status: RefundStatus::NEEDS_REVIEW,
                retryable: false,
                error: 'Missing VNPay transaction reference.',
            );
        }

        $payload = $payment->payload ?? [];

        $transactionNo = trim((string) ($payload['vnp_transaction_no'] ?? ''));
        if ($transactionNo === '') {
            $this->refunds->markNeedsReview($refund, 'Missing VNPay transaction number on the payment payload.');

            return new VnpayRefundResult(
                verified: false,
                status: RefundStatus::NEEDS_REVIEW,
                retryable: false,
                error: 'Missing VNPay transaction number on the payment payload.',
            );
        }

        $transactionDate = (string) ($payload['vnp_create_date'] ?? '');
        if (preg_match('/^\d{14}$/', $transactionDate) !== 1) {
            $transactionDate = $payment->created_at !== null
                ? $payment->created_at->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis')
                : '';
        }

        if (preg_match('/^\d{14}$/', $transactionDate) !== 1) {
            $this->refunds->markNeedsReview($refund, 'Missing VNPay transaction date for the original payment.');

            return new VnpayRefundResult(
                verified: false,
                status: RefundStatus::NEEDS_REVIEW,
                retryable: false,
                error: 'Missing VNPay transaction date for the original payment.',
            );
        }

        $type = RefundType::tryFrom((string) $refund->type);
        if ($type === null) {
            $this->refunds->markNeedsReview($refund, 'Invalid refund type.');

            return new VnpayRefundResult(
                verified: false,
                status: RefundStatus::NEEDS_REVIEW,
                retryable: false,
                error: 'Invalid refund type.',
            );
        }

        return new VnpayRefundData(
            requestId: (string) $refund->request_id,
            txnRef: $txnRef,
            amount: (float) $refund->amount,
            type: $type,
            transactionDate: $transactionDate,
            orderInfo: Str::limit('Hoan tien GD '.$txnRef, 255),
            transactionNo: $transactionNo,
        );
    }

    private function applyResult(PaymentRefund $refund, VnpayRefundResult $result): void
    {
        if ($result->retryable) {
            $this->scheduleRetry($refund, $result);

            return;
        }

        if ($result->providerAccepted()) {
            $this->applyAccepted($refund, $result);

            return;
        }

        $attributes = $this->resultAttributes($result);
        $error = $result->error ?? $result->message ?? 'VNPay refund did not complete.';

        match ($result->status) {
            RefundStatus::REJECTED => $this->refunds->markRejected($refund, Str::limit($error, 500), $attributes),
            RefundStatus::FAILED => $this->refunds->markFailed($refund, Str::limit($error, 500), $attributes),
            default => $this->refunds->markNeedsReview($refund, Str::limit($error, 500), $attributes),
        };
    }

    private function applyAccepted(PaymentRefund $refund, VnpayRefundResult $result): void
    {
        $attributes = $this->resultAttributes($result);

        DB::transaction(function () use ($refund, $result, $attributes): void {
            $fresh = $this->refunds->findByIdForUpdate((int) $refund->id);
            if ($fresh === null) {
                return;
            }

            if ($fresh->status === RefundStatus::SUCCEEDED->value || $this->isAcceptedProcessing($fresh)) {
                return;
            }

            $locked = $this->lockPaymentForRefund($fresh);
            if ($locked === null) {
                $this->refunds->markNeedsReview($fresh, 'Payment not found while applying the refund.');

                return;
            }

            $payload = $locked->payload ?? [];
            $alreadyCredited = collect($payload['refunds'] ?? [])
                ->contains(fn (mixed $entry): bool => is_array($entry)
                    && (int) ($entry['refund_id'] ?? 0) === (int) $fresh->id);

            if ($alreadyCredited) {
                if ($result->status === RefundStatus::SUCCEEDED) {
                    $this->refunds->markSucceeded($fresh, $attributes);
                } else {
                    $this->refunds->markAcceptedProcessing($fresh, $attributes);
                }

                return;
            }

            $newRefunded = round((float) ($locked->refunded_amount ?? 0) + (float) $fresh->amount, 2);
            if ($newRefunded > (float) $locked->amount + 0.001) {
                $this->refunds->markNeedsReview($fresh, 'Refund would exceed the original payment amount.', $attributes);

                return;
            }

            $payload['refunds'][] = [
                'amount' => (float) $fresh->amount,
                'reason' => $fresh->reason,
                'refunded_at' => now()->toIso8601String(),
                'refund_id' => $fresh->id,
                'request_id' => $fresh->request_id,
                'status' => $result->status->value,
            ];

            $paymentAttributes = [
                'refunded_amount' => $newRefunded,
                'status' => $newRefunded + 0.001 >= (float) $locked->amount
                    ? PaymentStatus::REFUNDED->value
                    : PaymentStatus::COMPLETED->value,
                'payload' => $payload,
            ];

            if ($locked instanceof Payment) {
                $this->hotelPayments->update($locked, $paymentAttributes);
            } else {
                $this->tourPayments->update($locked, $paymentAttributes);
            }

            if ($result->status === RefundStatus::SUCCEEDED) {
                $this->refunds->markSucceeded($fresh, $attributes);
            } else {
                $this->refunds->markAcceptedProcessing($fresh, $attributes);
            }
        });
    }

    private function lockPaymentForRefund(PaymentRefund $refund): Payment|TourPayment|null
    {
        return $refund->payment_type === PaymentRefund::TYPE_TOUR
            ? $this->tourPayments->findByIdForUpdate((int) $refund->payment_id)
            : $this->hotelPayments->findByIdForUpdate((int) $refund->payment_id);
    }

    private function scheduleRetry(PaymentRefund $refund, VnpayRefundResult $result): void
    {
        $error = $result->error ?? $result->message ?? 'Transient VNPay failure.';
        $attempts = (int) $refund->attempts + 1;
        $maxAttempts = max(1, (int) config('services.vnpay.refund_max_attempts', 5));

        if ($attempts >= $maxAttempts) {
            $this->refunds->markNeedsReview(
                $refund,
                Str::limit('Retry limit reached: '.$error, 500),
                $this->resultAttributes($result),
            );

            return;
        }

        $delayMinutes = (int) min(30, 2 ** max(0, $attempts - 1));

        $this->refunds->scheduleRetry(
            $refund,
            now()->addMinutes($delayMinutes),
            Str::limit($error, 500),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resultAttributes(VnpayRefundResult $result): array
    {
        return array_filter([
            'vnp_response_code' => $result->responseCode,
            'vnp_transaction_status' => $result->transactionStatus,
            'vnp_refund_transaction_no' => $result->refundTransactionNo,
            'vnp_pay_date' => $result->payDate,
            'provider_response' => $result->providerResponse,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Structured outcome log: identifiers and provider codes only, never payload/secrets.
     */
    private function logOutcome(PaymentRefund $refund): void
    {
        $refund->refresh();

        $context = [
            'refund_id' => $refund->id,
            'payment_type' => $refund->payment_type,
            'payment_id' => $refund->payment_id,
            'request_id' => $refund->request_id,
            'status' => $refund->status,
            'response_code' => $refund->vnp_response_code,
            'transaction_status' => $refund->vnp_transaction_status,
            'attempts' => (int) $refund->attempts,
            'next_retry_at' => $refund->next_retry_at?->toIso8601String(),
            'error' => $refund->last_error !== null ? Str::limit($refund->last_error, 200) : null,
        ];

        $level = in_array($refund->status, [
            RefundStatus::FAILED->value,
            RefundStatus::REJECTED->value,
            RefundStatus::NEEDS_REVIEW->value,
        ], true) ? 'warning' : 'info';

        Log::log($level, 'refund.outcome', $context);
    }
}
