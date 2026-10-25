<?php

use App\Enums\RefundStatus;
use App\Models\PaymentRefund;
use App\Repositories\Contracts\PaymentRefundRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->repo = app(PaymentRefundRepositoryInterface::class);
});

function makePaymentRefund(array $overrides = []): PaymentRefund
{
    return PaymentRefund::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'payment_type' => PaymentRefund::TYPE_HOTEL,
        'payment_id' => 10,
        'provider' => 'vnpay',
        'request_id' => (string) Str::uuid()->getHex(),
        'idempotency_key' => 'hotel-cancel:1:10',
        'txn_ref' => 'TRAVEL-REFUND-1',
        'amount' => 100000,
        'currency' => 'VND',
        'type' => '02',
        'status' => RefundStatus::PENDING->value,
    ], $overrides));
}

test('payment refunds migration creates the expected table and columns', function (): void {
    expect(Schema::hasTable('payment_refunds'))->toBeTrue()
        ->and(Schema::hasColumns('payment_refunds', [
            'id', 'uuid', 'payment_type', 'payment_id', 'provider', 'request_id',
            'idempotency_key', 'txn_ref', 'transaction_no', 'transaction_date',
            'amount', 'currency', 'type', 'status', 'attempts', 'next_retry_at',
            'locked_at', 'lease_expires_at', 'last_error', 'vnp_response_code',
            'vnp_transaction_status', 'vnp_refund_transaction_no', 'vnp_pay_date',
            'provider_response', 'reason', 'requested_by', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('createIfAbsent returns the existing refund for the same idempotency key', function (): void {
    $first = $this->repo->createIfAbsent([
        'payment_type' => PaymentRefund::TYPE_HOTEL,
        'payment_id' => 11,
        'idempotency_key' => 'hotel-cancel:2:11',
        'txn_ref' => 'TRAVEL-REFUND-2',
        'amount' => 100000,
        'type' => '02',
    ]);

    $second = $this->repo->createIfAbsent([
        'payment_type' => PaymentRefund::TYPE_HOTEL,
        'payment_id' => 11,
        'idempotency_key' => 'hotel-cancel:2:11',
        'txn_ref' => 'TRAVEL-REFUND-2',
        'amount' => 50000,
        'type' => '03',
    ]);

    expect($second->id)->toBe($first->id)
        ->and((float) $second->amount)->toBe(100000.0)
        ->and($first->uuid)->not->toBeEmpty()
        ->and($first->request_id)->toHaveLength(32)
        ->and($first->provider)->toBe('vnpay')
        ->and($first->currency)->toBe('VND')
        ->and($first->status)->toBe(RefundStatus::PENDING->value)
        ->and($first->attempts)->toBe(0);
});

test('createIfAbsent creates a new refund for a different idempotency key', function (): void {
    $first = $this->repo->createIfAbsent([
        'payment_type' => PaymentRefund::TYPE_HOTEL,
        'payment_id' => 12,
        'idempotency_key' => 'hotel-cancel:3:12',
        'txn_ref' => 'TRAVEL-REFUND-3',
        'amount' => 100000,
        'type' => '02',
    ]);

    $second = $this->repo->createIfAbsent([
        'payment_type' => PaymentRefund::TYPE_HOTEL,
        'payment_id' => 12,
        'idempotency_key' => 'hotel-cancel:3:12-b',
        'txn_ref' => 'TRAVEL-REFUND-3',
        'amount' => 20000,
        'type' => '03',
    ]);

    expect($second->id)->not->toBe($first->id)
        ->and($second->request_id)->not->toBe($first->request_id);
});

test('request id is unique', function (): void {
    makePaymentRefund(['request_id' => 'DUPLICATE-REQUEST-ID']);

    expect(fn () => makePaymentRefund([
        'payment_id' => 99,
        'idempotency_key' => 'hotel-cancel:other:99',
        'request_id' => 'DUPLICATE-REQUEST-ID',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('idempotency key is unique per payment', function (): void {
    makePaymentRefund([
        'payment_id' => 13,
        'idempotency_key' => 'hotel-cancel:4:13',
    ]);

    expect(fn () => makePaymentRefund([
        'payment_id' => 13,
        'idempotency_key' => 'hotel-cancel:4:13',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('findActiveForPayment returns the newest active refund only', function (): void {
    $failed = makePaymentRefund([
        'payment_id' => 14,
        'idempotency_key' => 'hotel-cancel:5:14',
        'status' => RefundStatus::FAILED->value,
    ]);

    $succeeded = makePaymentRefund([
        'payment_id' => 14,
        'idempotency_key' => 'hotel-cancel:6:14',
        'status' => RefundStatus::SUCCEEDED->value,
    ]);

    makePaymentRefund([
        'payment_type' => PaymentRefund::TYPE_TOUR,
        'payment_id' => 14,
        'idempotency_key' => 'tour-cancel:1:14',
    ]);

    $active = $this->repo->findActiveForPayment(PaymentRefund::TYPE_HOTEL, 14);

    expect($active?->id)->toBe($succeeded->id)
        ->and($active?->id)->not->toBe($failed->id)
        ->and($this->repo->findActiveForPayment(PaymentRefund::TYPE_HOTEL, 999))->toBeNull();
});

test('sumReserved only counts pending refunds', function (): void {
    makePaymentRefund([
        'payment_id' => 15,
        'idempotency_key' => 'a',
        'amount' => 100000,
        'status' => RefundStatus::PENDING->value,
    ]);
    makePaymentRefund([
        'payment_id' => 15,
        'idempotency_key' => 'b',
        'amount' => 50000,
        'status' => RefundStatus::PROCESSING->value,
    ]);
    makePaymentRefund([
        'payment_id' => 15,
        'idempotency_key' => 'c',
        'amount' => 25000,
        'status' => RefundStatus::SUCCEEDED->value,
    ]);
    makePaymentRefund([
        'payment_id' => 15,
        'idempotency_key' => 'd',
        'amount' => 10000,
        'status' => RefundStatus::FAILED->value,
    ]);

    expect($this->repo->sumReserved(PaymentRefund::TYPE_HOTEL, 15))->toBe(100000.0)
        ->and($this->repo->sumReserved(PaymentRefund::TYPE_HOTEL, 999))->toBe(0.0);
});

test('claimBatch skips retries not due, active leases and exhausted attempts', function (): void {
    $now = now();

    $claimable = makePaymentRefund(['payment_id' => 20, 'idempotency_key' => 'a']);
    $futureRetry = makePaymentRefund([
        'payment_id' => 20,
        'idempotency_key' => 'b',
        'next_retry_at' => $now->copy()->addMinutes(5),
    ]);
    $leased = makePaymentRefund([
        'payment_id' => 20,
        'idempotency_key' => 'c',
        'lease_expires_at' => $now->copy()->addMinutes(5),
    ]);
    $stale = makePaymentRefund([
        'payment_id' => 20,
        'idempotency_key' => 'd',
        'lease_expires_at' => $now->copy()->subMinute(),
    ]);
    $exhausted = makePaymentRefund([
        'payment_id' => 20,
        'idempotency_key' => 'e',
        'attempts' => 5,
    ]);

    $claimed = $this->repo->claimBatch(10, 90);
    $claimedIds = $claimed->pluck('id')->all();

    expect($claimedIds)->toContain($claimable->id)
        ->and($claimedIds)->toContain($stale->id)
        ->and($claimedIds)->not->toContain($futureRetry->id)
        ->and($claimedIds)->not->toContain($leased->id)
        ->and($claimedIds)->not->toContain($exhausted->id);

    foreach ($claimed as $refund) {
        expect($refund->locked_at)->not->toBeNull()
            ->and($refund->lease_expires_at)->not->toBeNull();
    }
});

test('markProcessing starts the lease and success clears it', function (): void {
    $refund = makePaymentRefund(['payment_id' => 21, 'idempotency_key' => 'a']);

    $processing = $this->repo->markProcessing($refund);

    expect($processing->status)->toBe(RefundStatus::PROCESSING->value)
        ->and($processing->locked_at)->not->toBeNull()
        ->and($processing->lease_expires_at)->not->toBeNull();

    $succeeded = $this->repo->markSucceeded($processing, [
        'vnp_response_code' => '00',
        'vnp_transaction_status' => '05',
        'provider_response' => ['vnp_ResponseCode' => '00'],
    ]);

    expect($succeeded->status)->toBe(RefundStatus::SUCCEEDED->value)
        ->and($succeeded->locked_at)->toBeNull()
        ->and($succeeded->lease_expires_at)->toBeNull()
        ->and($succeeded->vnp_response_code)->toBe('00')
        ->and($succeeded->vnp_transaction_status)->toBe('05')
        ->and($succeeded->provider_response)->toBe(['vnp_ResponseCode' => '00']);
});

test('markFailed, markRejected and markNeedsReview record attempts and errors', function (): void {
    $refund = makePaymentRefund(['payment_id' => 22, 'idempotency_key' => 'a']);

    $failed = $this->repo->markFailed($refund, 'provider error');
    expect($failed->status)->toBe(RefundStatus::FAILED->value)
        ->and($failed->attempts)->toBe(1)
        ->and($failed->last_error)->toBe('provider error')
        ->and($failed->locked_at)->toBeNull();

    $rejected = $this->repo->markRejected($failed, 'refund count exceeded');
    expect($rejected->status)->toBe(RefundStatus::REJECTED->value)
        ->and($rejected->attempts)->toBe(2)
        ->and($rejected->last_error)->toBe('refund count exceeded');

    $review = $this->repo->markNeedsReview($rejected, 'missing transaction date');
    expect($review->status)->toBe(RefundStatus::NEEDS_REVIEW->value)
        ->and($review->attempts)->toBe(2)
        ->and($review->last_error)->toBe('missing transaction date')
        ->and($review->next_retry_at)->toBeNull();
});

test('scheduleRetry backs off and increments attempts', function (): void {
    $refund = makePaymentRefund(['payment_id' => 23, 'idempotency_key' => 'a']);
    $availableAt = now()->addMinutes(2);

    $retried = $this->repo->scheduleRetry($refund, $availableAt, 'timeout');

    expect($retried->attempts)->toBe(1)
        ->and($retried->last_error)->toBe('timeout')
        ->and($retried->next_retry_at?->timestamp)->toBe($availableAt->timestamp)
        ->and($retried->locked_at)->toBeNull()
        ->and($retried->lease_expires_at)->toBeNull();

    expect($this->repo->claimBatch(10)->pluck('id')->all())->not->toContain($refund->id);

    $this->travel(3)->minutes();

    expect($this->repo->claimBatch(10)->pluck('id')->all())->toContain($refund->id);
});

test('releaseStaleLeases clears only expired leases', function (): void {
    $stale = makePaymentRefund([
        'payment_id' => 24,
        'idempotency_key' => 'a',
        'status' => RefundStatus::PROCESSING->value,
        'lease_expires_at' => now()->subMinute(),
    ]);
    $active = makePaymentRefund([
        'payment_id' => 24,
        'idempotency_key' => 'b',
        'status' => RefundStatus::PROCESSING->value,
        'lease_expires_at' => now()->addMinutes(5),
    ]);

    expect($this->repo->releaseStaleLeases())->toBe(1)
        ->and($stale->fresh()->lease_expires_at)->toBeNull()
        ->and($active->fresh()->lease_expires_at)->not->toBeNull();
});

test('lookup helpers locate by uuid, idempotency key and latest row', function (): void {
    $first = makePaymentRefund(['payment_id' => 25, 'idempotency_key' => 'a']);
    $second = makePaymentRefund([
        'payment_id' => 25,
        'idempotency_key' => 'b',
        'status' => RefundStatus::FAILED->value,
    ]);

    expect($this->repo->findByUuid($first->uuid)?->id)->toBe($first->id)
        ->and($this->repo->findByIdempotencyKey(PaymentRefund::TYPE_HOTEL, 25, 'a')?->id)->toBe($first->id)
        ->and($this->repo->findByIdempotencyKey(PaymentRefund::TYPE_HOTEL, 25, 'missing'))->toBeNull()
        ->and($this->repo->findLatestForPayment(PaymentRefund::TYPE_HOTEL, 25)?->id)->toBe($second->id)
        ->and($this->repo->findLatestForPayment(PaymentRefund::TYPE_TOUR, 25))->toBeNull();
});

test('markAcceptedProcessing records acceptance and clears the lease', function (): void {
    $refund = makePaymentRefund(['payment_id' => 26, 'idempotency_key' => 'a']);
    $processing = $this->repo->markProcessing($refund, now()->addMinutes(5));

    $accepted = $this->repo->markAcceptedProcessing($processing, [
        'vnp_response_code' => '00',
        'vnp_transaction_status' => '05',
        'provider_response' => ['vnp_ResponseCode' => '00'],
    ]);

    expect($accepted->status)->toBe(RefundStatus::PROCESSING->value)
        ->and($accepted->attempts)->toBe(1)
        ->and($accepted->locked_at)->toBeNull()
        ->and($accepted->lease_expires_at)->toBeNull()
        ->and($accepted->next_retry_at)->toBeNull()
        ->and($accepted->last_error)->toBeNull()
        ->and($accepted->vnp_response_code)->toBe('00')
        ->and($accepted->provider_response)->toBe(['vnp_ResponseCode' => '00']);
});

test('markPendingForReplay resets attempts, errors and leases', function (): void {
    $refund = makePaymentRefund([
        'payment_id' => 27,
        'idempotency_key' => 'a',
        'status' => RefundStatus::FAILED->value,
        'attempts' => 3,
        'last_error' => 'provider error',
        'next_retry_at' => now()->addMinutes(5),
        'lease_expires_at' => now()->addMinutes(5),
    ]);

    $replayed = $this->repo->markPendingForReplay($refund);

    expect($replayed->status)->toBe(RefundStatus::PENDING->value)
        ->and($replayed->attempts)->toBe(0)
        ->and($replayed->last_error)->toBeNull()
        ->and($replayed->next_retry_at)->toBeNull()
        ->and($replayed->locked_at)->toBeNull()
        ->and($replayed->lease_expires_at)->toBeNull();
});

test('claimBatch skips provider-accepted processing refunds', function (): void {
    $accepted = makePaymentRefund([
        'payment_id' => 28,
        'idempotency_key' => 'a',
        'status' => RefundStatus::PROCESSING->value,
        'vnp_response_code' => '00',
        'lease_expires_at' => now()->subMinute(),
    ]);
    $retryable = makePaymentRefund([
        'payment_id' => 28,
        'idempotency_key' => 'b',
        'status' => RefundStatus::PROCESSING->value,
        'last_error' => 'timeout',
        'lease_expires_at' => now()->subMinute(),
    ]);

    $claimedIds = $this->repo->claimBatch(10)->pluck('id')->all();

    expect($claimedIds)->not->toContain($accepted->id)
        ->and($claimedIds)->toContain($retryable->id);
});

test('two workers cannot claim the same refund while the lease is active', function (): void {
    $refund = makePaymentRefund(['payment_id' => 29, 'idempotency_key' => 'a']);

    $firstWorker = $this->repo->claimBatch(10, 90);
    $secondWorker = $this->repo->claimBatch(10, 90);

    expect($firstWorker->pluck('id')->all())->toBe([$refund->id])
        ->and($secondWorker)->toHaveCount(0)
        ->and($refund->fresh()->locked_at)->not->toBeNull();
});

test('an expired lease can be reclaimed after release', function (): void {
    $refund = makePaymentRefund([
        'payment_id' => 30,
        'idempotency_key' => 'a',
        'status' => RefundStatus::PROCESSING->value,
        'lease_expires_at' => now()->subMinute(),
    ]);

    expect($this->repo->releaseStaleLeases())->toBe(1);

    $claimed = $this->repo->claimBatch(10, 90);

    expect($claimed->pluck('id')->all())->toBe([$refund->id])
        ->and($refund->fresh()->lease_expires_at?->isFuture())->toBeTrue();
});

test('reserved amount does not double count processing refunds already in refunded_amount', function (): void {
    makePaymentRefund([
        'payment_id' => 31,
        'idempotency_key' => 'a',
        'amount' => 100000,
        'status' => RefundStatus::PENDING->value,
    ]);
    makePaymentRefund([
        'payment_id' => 31,
        'idempotency_key' => 'b',
        'amount' => 200000,
        'status' => RefundStatus::PROCESSING->value,
    ]);

    expect($this->repo->sumReserved(PaymentRefund::TYPE_HOTEL, 31))->toBe(100000.0);
});
