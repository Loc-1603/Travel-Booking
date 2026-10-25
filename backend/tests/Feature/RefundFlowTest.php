<?php

use App\DTOs\VnpayRefundResult;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\ProcessVnpayRefund;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\RefundService;
use App\Services\VnpayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const REFUND_FLOW_SECRET = 'refund-flow-secret-1234567890';
const REFUND_FLOW_TXN_REF = 'REFUND-FLOW-TXN-1';

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => REFUND_FLOW_SECRET,
        'services.vnpay.version' => '2.1.0',
        'services.vnpay.api_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.refund_timeout' => 5,
        'services.vnpay.refund_max_attempts' => 5,
        'services.vnpay.refund_create_by' => 'system',
    ]);

    Queue::fake();

    $vendor = User::create([
        'name' => 'Refund Vendor', 'email' => 'refund-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $customer = User::create([
        'name' => 'Refund Customer', 'email' => 'refund-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Refund Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->booking = Booking::create([
        'uuid' => (string) Str::uuid(),
        'customer_id' => $customer->id,
        'hotel_id' => $this->hotel->id,
        'status' => 'confirmed',
        'check_in' => now()->addDays(7)->toDateString(),
        'check_out' => now()->addDays(9)->toDateString(),
        'total_price' => 300000,
        'currency' => 'VND',
    ]);

    $this->service = app(RefundService::class);
});

function refundFlowPayment(object $ctx, array $overrides = []): Payment
{
    return Payment::create(array_merge([
        'booking_id' => $ctx->booking->id,
        'amount' => 300000,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => REFUND_FLOW_TXN_REF,
        'status' => PaymentStatus::COMPLETED->value,
        'refunded_amount' => 0,
        'payload' => [
            'txn_ref' => REFUND_FLOW_TXN_REF,
            'vnp_transaction_no' => '15713760',
            'vnp_create_date' => '20261024101010',
        ],
    ], $overrides));
}

function refundFlowResponse(array $overrides = []): array
{
    $response = array_merge([
        'vnp_ResponseId' => 'refund-resp-1',
        'vnp_Command' => 'refund',
        'vnp_ResponseCode' => '00',
        'vnp_Message' => 'Success',
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_TxnRef' => REFUND_FLOW_TXN_REF,
        'vnp_Amount' => '30000000',
        'vnp_BankCode' => 'NCB',
        'vnp_PayDate' => '20261024120500',
        'vnp_TransactionNo' => '15713768',
        'vnp_TransactionType' => '03',
        'vnp_TransactionStatus' => '05',
        'vnp_OrderInfo' => 'Hoan tien GD '.REFUND_FLOW_TXN_REF,
    ], $overrides);

    $values = array_map(
        static fn (string $field): mixed => $response[$field] ?? '',
        VnpayRefundResult::CHECKSUM_FIELDS,
    );

    $response['vnp_SecureHash'] = hash_hmac('sha512', implode('|', $values), REFUND_FLOW_SECRET);

    return $response;
}

test('request persists a pending obligation without touching the payment', function (): void {
    $payment = refundFlowPayment($this);

    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:1:'.$payment->id, 7);

    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe(RefundStatus::PENDING->value)
        ->and((float) $refund->amount)->toBe(100000.0)
        ->and($refund->type)->toBe('03')
        ->and($refund->payment_type)->toBe(PaymentRefund::TYPE_HOTEL)
        ->and($refund->requested_by)->toBe(7)
        ->and($refund->request_id)->toHaveLength(32)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value);
});

test('a refund for the full payment uses the full transaction type', function (): void {
    $payment = refundFlowPayment($this);

    $refund = $this->service->request($payment, 300000, 'cancel', 'hotel-cancel:2:'.$payment->id);

    expect($refund?->type)->toBe('02');
});

test('the same idempotency key returns the existing obligation', function (): void {
    $payment = refundFlowPayment($this);

    $first = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:3:'.$payment->id);
    $second = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:3:'.$payment->id);

    expect($second?->id)->toBe($first?->id)
        ->and(PaymentRefund::count())->toBe(1);
});

test('v1 returns the existing obligation for a different key on the same payment', function (): void {
    $payment = refundFlowPayment($this);

    $first = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:4:'.$payment->id);
    $second = $this->service->request($payment, 50000, 'another', 'hotel-cancel:4b:'.$payment->id);

    expect($second?->id)->toBe($first?->id)
        ->and(PaymentRefund::count())->toBe(1);
});

test('request rejects an amount above the original payment', function (): void {
    $payment = refundFlowPayment($this);

    expect(fn () => $this->service->request($payment, 300001, 'cancel', 'hotel-cancel:5:'.$payment->id))
        ->toThrow(InvalidArgumentException::class);
});

test('an accepted refund 00/05 credits the payment and stays processing', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse())]);
    $payment = refundFlowPayment($this);

    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:6:'.$payment->id);
    $this->service->process((int) $refund?->id);

    $refund = $refund?->fresh();
    $payment = $payment->fresh();

    expect($refund?->status)->toBe(RefundStatus::PROCESSING->value)
        ->and($refund?->vnp_response_code)->toBe('00')
        ->and($refund?->vnp_transaction_status)->toBe('05')
        ->and($refund?->vnp_refund_transaction_no)->toBe('15713768')
        ->and($refund?->vnp_pay_date)->toBe('20261024120500')
        ->and($refund?->last_error)->toBeNull()
        ->and($refund?->provider_response)->not->toHaveKey('vnp_SecureHash')
        ->and((float) $payment?->refunded_amount)->toBe(100000.0)
        ->and($payment?->status)->toBe(PaymentStatus::COMPLETED->value);

    $this->service->process((int) $refund?->id);
    Http::assertSentCount(1);
});

test('an accepted refund 00/00 marks the refund succeeded and the payment refunded', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse([
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
    ]))]);
    $payment = refundFlowPayment($this);

    $refund = $this->service->request($payment, 300000, 'cancel', 'hotel-cancel:7:'.$payment->id);
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::SUCCEEDED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(300000.0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::REFUNDED->value)
        ->and($payment->fresh()->payload['refunds'][0]['refund_id'])->toBe($refund?->id);
});

test('a duplicated request 94 is treated as accepted without a second request id', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse([
        'vnp_ResponseCode' => '94',
        'vnp_Message' => 'Request is duplicated',
        'vnp_TransactionStatus' => '',
    ]))]);
    $payment = refundFlowPayment($this);

    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:8:'.$payment->id);
    $requestId = $refund?->request_id;
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::PROCESSING->value)
        ->and($refund?->fresh()->request_id)->toBe($requestId)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(100000.0);
});

test('a refund-count 94 rejects the obligation without crediting the payment', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse([
        'vnp_ResponseCode' => '94',
        'vnp_Message' => 'Too many refund count (>0)',
        'vnp_TransactionStatus' => '',
    ]))]);
    $payment = refundFlowPayment($this);

    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:9:'.$payment->id);
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::REJECTED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('missing original transaction data goes to review without calling VNPay', function (): void {
    Http::fake();
    $payment = refundFlowPayment($this, ['payload' => ['txn_ref' => REFUND_FLOW_TXN_REF]]);

    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:10:'.$payment->id);
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::NEEDS_REVIEW->value)
        ->and($refund?->fresh()->last_error)->toContain('transaction number')
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);

    Http::assertNothingSent();
});

test('an invalid response checksum is never trusted', function (): void {
    Http::fake(['*' => Http::response(array_merge(refundFlowResponse(), ['vnp_SecureHash' => 'deadbeef']))]);
    $payment = refundFlowPayment($this);

    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:11:'.$payment->id);
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::NEEDS_REVIEW->value)
        ->and($refund?->fresh()->last_error)->toContain('checksum')
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('transport failures retry with backoff and stop at the retry limit', function (): void {
    config(['services.vnpay.refund_max_attempts' => 2]);
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $payment = refundFlowPayment($this);

    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:12:'.$payment->id);
    $this->service->process((int) $refund?->id);

    $refund = $refund?->fresh();
    expect($refund?->status)->toBe(RefundStatus::PROCESSING->value)
        ->and($refund?->attempts)->toBe(1)
        ->and($refund?->next_retry_at)->not->toBeNull()
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);

    $this->travel(2)->minutes();
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::NEEDS_REVIEW->value)
        ->and($refund?->fresh()->last_error)->toContain('Retry limit')
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('a disabled flag leaves a queued obligation pending for later processing', function (): void {
    $payment = refundFlowPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:12:'.$payment->id);

    config(['services.vnpay.refund_enabled' => false]);
    $this->service->process((int) $refund?->id);

    Http::assertNothingSent();
    expect($refund?->fresh()->status)->toBe(RefundStatus::PENDING->value)
        ->and((int) $refund?->fresh()->attempts)->toBe(0)
        ->and($refund?->fresh()->lease_expires_at)->toBeNull()
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('flag off keeps the legacy local accounting and persists no obligation', function (): void {
    config(['services.vnpay.refund_enabled' => false]);
    Http::fake();
    $payment = refundFlowPayment($this);

    $result = $this->service->request($payment, 300000, 'cancel', 'hotel-cancel:13:'.$payment->id);

    expect($result)->toBeNull()
        ->and((float) $payment->fresh()->refunded_amount)->toBe(300000.0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::REFUNDED->value)
        ->and(PaymentRefund::count())->toBe(0);

    Http::assertNothingSent();
});

test('a non-vnpay payment is settled locally even when the flag is on', function (): void {
    $payment = refundFlowPayment($this, [
        'provider' => 'internal',
        'external_id' => null,
        'payload' => null,
    ]);

    $result = $this->service->request($payment, 100000, 'manual', 'manual:1:'.$payment->id);

    expect($result)->toBeNull()
        ->and((float) $payment->fresh()->refunded_amount)->toBe(100000.0)
        ->and(PaymentRefund::count())->toBe(0);
});

test('refunds:process claims and processes due obligations', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse())]);
    $payment = refundFlowPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:14:'.$payment->id);

    $this->artisan('refunds:process')->assertSuccessful();

    expect($refund?->fresh()->status)->toBe(RefundStatus::PROCESSING->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(100000.0);
});

test('refunds:retry replays the same request id after a failure', function (): void {
    Http::fakeSequence()
        ->push(refundFlowResponse([
            'vnp_ResponseCode' => '95',
            'vnp_Message' => 'Transaction not found',
            'vnp_TransactionStatus' => '',
        ]))
        ->push(refundFlowResponse());

    $payment = refundFlowPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:15:'.$payment->id);
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::FAILED->value);
    $requestId = $refund?->fresh()->request_id;

    $this->artisan('refunds:retry', ['refund' => $refund?->id])->assertSuccessful();

    expect($refund?->fresh()->status)->toBe(RefundStatus::PENDING->value)
        ->and($refund?->fresh()->request_id)->toBe($requestId);

    Queue::assertPushed(ProcessVnpayRefund::class, fn (ProcessVnpayRefund $job): bool => $job->refundId === $refund?->id);

    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::PROCESSING->value)
        ->and($refund?->fresh()->request_id)->toBe($requestId)
        ->and($refund?->fresh()->last_error)->toBeNull()
        ->and((float) $payment->fresh()->refunded_amount)->toBe(100000.0);
});

test('refunds:retry refuses an already accepted refund without force', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse())]);
    $payment = refundFlowPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:16:'.$payment->id);
    $this->service->process((int) $refund?->id);

    $this->artisan('refunds:retry', ['refund' => $refund?->id])
        ->expectsOutputToContain('already accepted')
        ->assertFailed();
});

test('a forced replay never credits the same refund twice', function (): void {
    Http::fakeSequence()
        ->push(refundFlowResponse())
        ->push(refundFlowResponse());

    $payment = refundFlowPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:17:'.$payment->id);
    $this->service->process((int) $refund?->id);

    $this->artisan('refunds:retry', ['refund' => $refund?->id, '--force' => true])->assertSuccessful();
    $this->service->process((int) $refund?->id);

    expect((float) $payment->fresh()->refunded_amount)->toBe(100000.0)
        ->and($refund?->fresh()->status)->toBe(RefundStatus::PROCESSING->value);
});

test('vnpay checkout and ipn persist the original transaction dates', function (): void {
    $payment = refundFlowPayment($this, ['payload' => null]);

    $result = app(VnpayService::class)->createPaymentUrl($this->booking, '127.0.0.1');
    $created = Payment::findOrFail($result['payment_id']);

    expect($created->payload['vnp_create_date'])->toMatch('/^\d{14}$/')
        ->and($created->payload['txn_ref'])->toBe($result['txn_ref']);

    app(VnpayService::class)->recordTransactionNo($payment, [
        'vnp_TransactionNo' => '15713760',
        'vnp_PayDate' => '20261024120500',
    ]);

    expect($payment->fresh()->payload['vnp_transaction_no'])->toBe('15713760')
        ->and($payment->fresh()->payload['vnp_pay_date'])->toBe('20261024120500');
});

test('refund lifecycle emits structured logs without provider payloads', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse())]);
    $payment = refundFlowPayment($this);

    Log::spy();

    $refund = $this->service->request($payment, 100000, 'cancel', 'hotel-cancel:log:'.$payment->id);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($refund, $payment): bool {
            return $message === 'refund.obligation_created'
                && $context['refund_id'] === $refund?->id
                && $context['payment_id'] === $payment->id
                && $context['request_id'] === $refund?->request_id;
        })
        ->once();

    $this->service->process((int) $refund?->id);

    Log::shouldHaveReceived('log')
        ->withArgs(function (string $level, string $message, array $context) use ($refund, $payment): bool {
            return $level === 'info'
                && $message === 'refund.outcome'
                && $context['refund_id'] === $refund?->id
                && $context['payment_id'] === $payment->id
                && $context['status'] === RefundStatus::PROCESSING->value
                && $context['response_code'] === '00'
                && $context['attempts'] === 1
                && ! array_key_exists('provider_response', $context);
        })
        ->once();
});

test('refunds:settle records an off-platform refund for a needs_review obligation', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse([
        'vnp_ResponseCode' => '02',
        'vnp_Message' => 'Invalid amount',
        'vnp_TransactionStatus' => '',
    ]))]);

    $payment = refundFlowPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'settle:1:'.$payment->id);
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::NEEDS_REVIEW->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);

    $this->artisan('refunds:settle', [
        'refund' => $refund?->id,
        '--note' => 'Bank transfer FT-2026-001',
        '--operator' => 'ops@test.local',
    ])->assertSuccessful();

    $fresh = $payment->fresh();
    $entry = collect($fresh->payload['refunds'] ?? [])->firstWhere('refund_id', $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::SUCCEEDED->value)
        ->and((float) $fresh->refunded_amount)->toBe(100000.0)
        ->and($fresh->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($entry)->not->toBeNull()
        ->and($entry['manual'])->toBeTrue()
        ->and($entry['note'])->toBe('Bank transfer FT-2026-001')
        ->and($entry['operator'])->toBe('ops@test.local')
        ->and($refund?->fresh()->provider_response['manual_settlement']['note'])->toBe('Bank transfer FT-2026-001');
});

test('refunds:settle refuses open obligations, missing notes and double settlement', function (): void {
    Http::fake(['*' => Http::response(refundFlowResponse([
        'vnp_ResponseCode' => '02',
        'vnp_Message' => 'Invalid amount',
        'vnp_TransactionStatus' => '',
    ]))]);

    $payment = refundFlowPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'settle:2:'.$payment->id);

    $this->artisan('refunds:settle', ['refund' => $refund?->id, '--note' => 'too early'])->assertFailed();
    expect($refund?->fresh()->status)->toBe(RefundStatus::PENDING->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);

    $this->service->process((int) $refund?->id);

    $this->artisan('refunds:settle', ['refund' => $refund?->id])->assertFailed();
    $this->artisan('refunds:settle', ['refund' => $refund?->id, '--note' => 'manual 1'])->assertSuccessful();
    $this->artisan('refunds:settle', ['refund' => $refund?->id, '--note' => 'manual 2'])->assertFailed();

    expect((float) $payment->fresh()->refunded_amount)->toBe(100000.0)
        ->and($refund?->fresh()->status)->toBe(RefundStatus::SUCCEEDED->value);
});
