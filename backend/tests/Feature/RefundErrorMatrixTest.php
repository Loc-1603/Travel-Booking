<?php

use App\DTOs\VnpayRefundResult;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const REFUND_MATRIX_SECRET = 'refund-matrix-secret-1234567890';
const REFUND_MATRIX_TXN_REF = 'REFUND-MATRIX-TXN-1';

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => REFUND_MATRIX_SECRET,
        'services.vnpay.version' => '2.1.0',
        'services.vnpay.api_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.refund_timeout' => 5,
        'services.vnpay.refund_max_attempts' => 5,
        'services.vnpay.refund_create_by' => 'system',
    ]);

    Queue::fake();

    $vendor = User::create([
        'name' => 'Matrix Vendor', 'email' => 'matrix-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $customer = User::create([
        'name' => 'Matrix Customer', 'email' => 'matrix-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Matrix Hotel',
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

function refundMatrixPayment(object $ctx, array $overrides = []): Payment
{
    return Payment::create(array_merge([
        'booking_id' => $ctx->booking->id,
        'amount' => 300000,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => REFUND_MATRIX_TXN_REF,
        'status' => PaymentStatus::COMPLETED->value,
        'refunded_amount' => 0,
        'payload' => [
            'txn_ref' => REFUND_MATRIX_TXN_REF,
            'vnp_transaction_no' => '15713760',
            'vnp_create_date' => '20261024101010',
        ],
    ], $overrides));
}

function refundMatrixResponse(array $overrides = []): array
{
    $response = array_merge([
        'vnp_ResponseId' => 'matrix-resp-1',
        'vnp_Command' => 'refund',
        'vnp_ResponseCode' => '00',
        'vnp_Message' => 'Success',
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_TxnRef' => REFUND_MATRIX_TXN_REF,
        'vnp_Amount' => '30000000',
        'vnp_BankCode' => 'NCB',
        'vnp_PayDate' => '20261024120500',
        'vnp_TransactionNo' => '15713768',
        'vnp_TransactionType' => '03',
        'vnp_TransactionStatus' => '05',
        'vnp_OrderInfo' => 'Hoan tien GD '.REFUND_MATRIX_TXN_REF,
    ], $overrides);

    $values = array_map(
        static fn (string $field): mixed => $response[$field] ?? '',
        VnpayRefundResult::CHECKSUM_FIELDS,
    );

    $response['vnp_SecureHash'] = hash_hmac('sha512', implode('|', $values), REFUND_MATRIX_SECRET);

    return $response;
}

function processRefundMatrixPayment(object $ctx, string $key): PaymentRefund
{
    $payment = refundMatrixPayment($ctx);
    $refund = $ctx->service->request($payment, 100000, 'cancel', $key.':'.$payment->id);

    $ctx->service->process((int) $refund?->id);

    return $refund?->fresh();
}

test('provider outcomes map to obligation status and accounting', function (
    string $responseCode,
    string $transactionStatus,
    string $expectedStatus,
    bool $credited,
): void {
    Http::fake(['*' => Http::response(refundMatrixResponse([
        'vnp_ResponseCode' => $responseCode,
        'vnp_TransactionStatus' => $transactionStatus,
        'vnp_Message' => 'Outcome '.$responseCode.'/'.$transactionStatus,
    ]))]);

    $payment = refundMatrixPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'matrix:'.$responseCode.':'.$transactionStatus.':'.$payment->id);
    $this->service->process((int) $refund?->id);

    $refund = $refund?->fresh();
    $payment = $payment->fresh();

    expect($refund?->status)->toBe($expectedStatus)
        ->and($refund?->vnp_response_code)->toBe($responseCode)
        ->and($refund?->provider_response)->not->toHaveKey('vnp_SecureHash')
        ->and((float) $payment?->refunded_amount)->toBe($credited ? 100000.0 : 0.0)
        ->and($payment?->status)->toBe(PaymentStatus::COMPLETED->value);

    // A terminal or accepted obligation must never be sent to VNPay twice.
    $this->service->process((int) $refund?->id);
    Http::assertSentCount(1);
})->with([
    'settled' => ['00', '00', RefundStatus::SUCCEEDED->value, true],
    'provider processing 05' => ['00', '05', RefundStatus::PROCESSING->value, true],
    'provider processing 06' => ['00', '06', RefundStatus::PROCESSING->value, true],
    'provider processing 01' => ['00', '01', RefundStatus::PROCESSING->value, true],
    'provider rejected 09' => ['00', '09', RefundStatus::REJECTED->value, false],
    'provider review 02' => ['00', '02', RefundStatus::NEEDS_REVIEW->value, false],
    'provider review 04' => ['00', '04', RefundStatus::NEEDS_REVIEW->value, false],
    'provider review 07' => ['00', '07', RefundStatus::NEEDS_REVIEW->value, false],
    'not found 91' => ['91', '', RefundStatus::FAILED->value, false],
    'original failed 95' => ['95', '', RefundStatus::FAILED->value, false],
    'rejected 09' => ['09', '', RefundStatus::REJECTED->value, false],
    'bad format 02' => ['02', '', RefundStatus::NEEDS_REVIEW->value, false],
    'bad format 03' => ['03', '', RefundStatus::NEEDS_REVIEW->value, false],
    'bad checksum 97' => ['97', '', RefundStatus::NEEDS_REVIEW->value, false],
]);

test('a duplicated request is accepted and credited once', function (): void {
    Http::fake(['*' => Http::response(refundMatrixResponse([
        'vnp_ResponseCode' => '94',
        'vnp_Message' => 'Request is duplicated',
        'vnp_TransactionStatus' => '',
    ]))]);

    $payment = refundMatrixPayment($this);
    $refund = $this->service->request($payment, 100000, 'cancel', 'matrix:94:dup:'.$payment->id);
    $requestId = $refund?->request_id;
    $this->service->process((int) $refund?->id);

    expect($refund?->fresh()->status)->toBe(RefundStatus::PROCESSING->value)
        ->and($refund?->fresh()->request_id)->toBe($requestId)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(100000.0);

    $this->service->process((int) $refund?->id);
    Http::assertSentCount(1);
});

test('a provider refund-count limit rejects the obligation without credit', function (): void {
    Http::fake(['*' => Http::response(refundMatrixResponse([
        'vnp_ResponseCode' => '94',
        'vnp_Message' => 'Too many refund count (>0)',
        'vnp_TransactionStatus' => '',
    ]))]);

    $refund = processRefundMatrixPayment($this, 'matrix:94:limit');

    expect($refund->status)->toBe(RefundStatus::REJECTED->value)
        ->and($refund->last_error)->toContain('Too many refund count')
        ->and((float) Payment::find($refund->payment_id)?->refunded_amount)->toBe(0.0);
});

test('retryable provider responses schedule a backoff instead of crediting', function (string $message): void {
    if ($message === 'timeout') {
        Http::fake(fn () => throw new ConnectionException('timeout'));
    } elseif ($message === 'server-error') {
        Http::fake(['*' => Http::response('unavailable', 503)]);
    } else {
        Http::fake(['*' => Http::response(refundMatrixResponse([
            'vnp_ResponseCode' => '99',
            'vnp_Message' => 'Unknown error',
            'vnp_TransactionStatus' => '',
        ]))]);
    }

    $refund = processRefundMatrixPayment($this, 'matrix:retry:'.$message);

    expect($refund->status)->toBe(RefundStatus::PROCESSING->value)
        ->and($refund->attempts)->toBe(1)
        ->and($refund->next_retry_at)->not->toBeNull()
        ->and($refund->last_error)->not->toBeNull()
        ->and((float) Payment::find($refund->payment_id)?->refunded_amount)->toBe(0.0);
})->with(['timeout', 'server-error', '99']);

test('an untrusted checksum goes to review without credit or retry', function (): void {
    Http::fake(['*' => Http::response(array_merge(refundMatrixResponse(), [
        'vnp_SecureHash' => 'deadbeef',
    ]))]);

    $refund = processRefundMatrixPayment($this, 'matrix:checksum');

    expect($refund->status)->toBe(RefundStatus::NEEDS_REVIEW->value)
        ->and($refund->last_error)->toContain('checksum')
        ->and($refund->next_retry_at)->toBeNull()
        ->and((float) Payment::find($refund->payment_id)?->refunded_amount)->toBe(0.0);

    $this->service->process((int) $refund->id);
    Http::assertSentCount(1);
});
