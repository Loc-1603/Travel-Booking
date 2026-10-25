<?php

use App\DTOs\VnpayQueryData;
use App\DTOs\VnpayRefundData;
use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Services\VnpayRefundClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const VNPAY_TEST_SECRET = 'test-secret-1234567890';

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => VNPAY_TEST_SECRET,
        'services.vnpay.version' => '2.1.0',
        'services.vnpay.api_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.refund_timeout' => 15,
        'services.vnpay.refund_create_by' => 'system',
    ]);

    $this->client = app(VnpayRefundClient::class);
});

function vnpayRefundSign(array $values): string
{
    $normalized = array_map(
        static fn (mixed $value): string => $value === null ? '' : (string) $value,
        $values,
    );

    return hash_hmac('sha512', implode('|', $normalized), VNPAY_TEST_SECRET);
}

function vnpayRefundData(array $overrides = []): VnpayRefundData
{
    return new VnpayRefundData(
        requestId: $overrides['requestId'] ?? 'REQ-1',
        txnRef: $overrides['txnRef'] ?? '100_20261024_ABCD',
        amount: $overrides['amount'] ?? 100000.0,
        type: $overrides['type'] ?? RefundType::PARTIAL,
        transactionDate: $overrides['transactionDate'] ?? '20261024101010',
        orderInfo: $overrides['orderInfo'] ?? 'Refund booking',
        transactionNo: array_key_exists('transactionNo', $overrides) ? $overrides['transactionNo'] : '15713760',
        createBy: $overrides['createBy'] ?? 'ops@test.local',
        ipAddr: $overrides['ipAddr'] ?? '127.0.0.1',
        createDate: $overrides['createDate'] ?? '20261024120000',
    );
}

function vnpayQueryData(array $overrides = []): VnpayQueryData
{
    return new VnpayQueryData(
        requestId: $overrides['requestId'] ?? 'QRY-1',
        txnRef: $overrides['txnRef'] ?? '100_20261024_ABCD',
        transactionDate: $overrides['transactionDate'] ?? '20261024101010',
        orderInfo: $overrides['orderInfo'] ?? 'Query booking',
        ipAddr: $overrides['ipAddr'] ?? '127.0.0.1',
        createDate: $overrides['createDate'] ?? '20261024120000',
    );
}

/**
 * VNPay refund response checksum order (documented contract).
 *
 * @return array<int, string>
 */
function vnpayRefundResponseFields(): array
{
    return [
        'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message',
        'vnp_TmnCode', 'vnp_TxnRef', 'vnp_Amount', 'vnp_BankCode', 'vnp_PayDate',
        'vnp_TransactionNo', 'vnp_TransactionType', 'vnp_TransactionStatus', 'vnp_OrderInfo',
    ];
}

/**
 * VNPay querydr response checksum order (documented contract).
 *
 * @return array<int, string>
 */
function vnpayQueryResponseFields(): array
{
    return [
        ...vnpayRefundResponseFields(),
        'vnp_PromotionCode',
        'vnp_PromotionAmount',
    ];
}

function vnpayRefundResponse(array $overrides = []): array
{
    $response = array_merge([
        'vnp_ResponseId' => 'resp-1',
        'vnp_Command' => 'refund',
        'vnp_ResponseCode' => '00',
        'vnp_Message' => 'Success',
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_TxnRef' => '100_20261024_ABCD',
        'vnp_Amount' => '10000000',
        'vnp_BankCode' => 'NCB',
        'vnp_PayDate' => '20261024120500',
        'vnp_TransactionNo' => '15713768',
        'vnp_TransactionType' => '03',
        'vnp_TransactionStatus' => '05',
        'vnp_OrderInfo' => 'Refund booking',
    ], $overrides);

    $values = array_map(static fn (string $field): mixed => $response[$field] ?? '', vnpayRefundResponseFields());
    $response['vnp_SecureHash'] = vnpayRefundSign($values);

    return $response;
}

function vnpayQueryResponse(array $overrides = []): array
{
    $response = array_merge([
        'vnp_ResponseId' => 'qry-1',
        'vnp_Command' => 'querydr',
        'vnp_ResponseCode' => '00',
        'vnp_Message' => 'Success',
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_TxnRef' => '100_20261024_ABCD',
        'vnp_Amount' => '10000000',
        'vnp_BankCode' => 'NCB',
        'vnp_PayDate' => '20261024101010',
        'vnp_TransactionNo' => '15713760',
        'vnp_TransactionType' => '01',
        'vnp_TransactionStatus' => '01',
        'vnp_OrderInfo' => 'Query booking',
        'vnp_PromotionCode' => '',
        'vnp_PromotionAmount' => '',
    ], $overrides);

    $values = array_map(static fn (string $field): mixed => $response[$field] ?? '', vnpayQueryResponseFields());
    $response['vnp_SecureHash'] = vnpayRefundSign($values);

    return $response;
}

test('refund sends a signed JSON request and maps an accepted response', function (): void {
    Http::fake(['*' => Http::response(vnpayRefundResponse())]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->verified)->toBeTrue()
        ->and($result->status)->toBe(RefundStatus::PROCESSING)
        ->and($result->responseCode)->toBe('00')
        ->and($result->transactionStatus)->toBe('05')
        ->and($result->refundTransactionNo)->toBe('15713768')
        ->and($result->payDate)->toBe('20261024120500')
        ->and($result->amount)->toBe(100000)
        ->and($result->providerAccepted())->toBeTrue()
        ->and($result->providerResponse)->not->toHaveKey('vnp_SecureHash');

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        expect($request->url())->toBe('https://sandbox.vnpayment.vn/merchant_webapi/api/transaction')
            ->and($body['vnp_RequestId'])->toBe('REQ-1')
            ->and($body['vnp_Version'])->toBe('2.1.0')
            ->and($body['vnp_Command'])->toBe('refund')
            ->and($body['vnp_TmnCode'])->toBe('TESTCODE')
            ->and($body['vnp_TransactionType'])->toBe('03')
            ->and($body['vnp_TxnRef'])->toBe('100_20261024_ABCD')
            ->and($body['vnp_Amount'])->toBe(10000000)
            ->and($body['vnp_TransactionNo'])->toBe('15713760')
            ->and($body['vnp_TransactionDate'])->toBe('20261024101010')
            ->and($body['vnp_CreateBy'])->toBe('ops@test.local')
            ->and($body['vnp_CreateDate'])->toBe('20261024120000')
            ->and($body['vnp_IpAddr'])->toBe('127.0.0.1')
            ->and($body['vnp_OrderInfo'])->toBe('Refund booking');

        $expected = vnpayRefundSign([
            $body['vnp_RequestId'],
            $body['vnp_Version'],
            $body['vnp_Command'],
            $body['vnp_TmnCode'],
            $body['vnp_TransactionType'],
            $body['vnp_TxnRef'],
            $body['vnp_Amount'],
            $body['vnp_TransactionNo'],
            $body['vnp_TransactionDate'],
            $body['vnp_CreateBy'],
            $body['vnp_CreateDate'],
            $body['vnp_IpAddr'],
            $body['vnp_OrderInfo'],
        ]);

        expect($body['vnp_SecureHash'])->toBe($expected);

        return true;
    });
});

test('refund signs an empty transaction number segment when it is not provided', function (): void {
    Http::fake(['*' => Http::response(vnpayRefundResponse())]);

    $this->client->refund(vnpayRefundData(['transactionNo' => null]));

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        expect($body['vnp_TransactionNo'])->toBe('');

        $expected = vnpayRefundSign([
            $body['vnp_RequestId'],
            $body['vnp_Version'],
            $body['vnp_Command'],
            $body['vnp_TmnCode'],
            $body['vnp_TransactionType'],
            $body['vnp_TxnRef'],
            $body['vnp_Amount'],
            '',
            $body['vnp_TransactionDate'],
            $body['vnp_CreateBy'],
            $body['vnp_CreateDate'],
            $body['vnp_IpAddr'],
            $body['vnp_OrderInfo'],
        ]);

        expect($body['vnp_SecureHash'])->toBe($expected);

        return true;
    });
});

test('refund maps a fully settled provider response to succeeded', function (): void {
    Http::fake(['*' => Http::response(vnpayRefundResponse(['vnp_TransactionStatus' => '00']))]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->status)->toBe(RefundStatus::SUCCEEDED)
        ->and($result->retryable)->toBeFalse()
        ->and($result->providerAccepted())->toBeTrue();
});

test('refund maps provider status 05 or 06 to processing', function (string $transactionStatus): void {
    Http::fake(['*' => Http::response(vnpayRefundResponse(['vnp_TransactionStatus' => $transactionStatus]))]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->status)->toBe(RefundStatus::PROCESSING)
        ->and($result->retryable)->toBeFalse()
        ->and($result->providerAccepted())->toBeTrue();
})->with(['05', '06', '01']);

test('refund treats a duplicated request (94) as processing without retry', function (): void {
    Http::fake(['*' => Http::response(vnpayRefundResponse([
        'vnp_ResponseCode' => '94',
        'vnp_Message' => 'Request is duplicated',
        'vnp_TransactionStatus' => '',
    ]))]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->status)->toBe(RefundStatus::PROCESSING)
        ->and($result->retryable)->toBeFalse()
        ->and($result->providerAccepted())->toBeTrue();
});

test('refund rejects a second obligation on a payment (94 too many refund count)', function (): void {
    Http::fake(['*' => Http::response(vnpayRefundResponse([
        'vnp_ResponseCode' => '94',
        'vnp_Message' => 'Too many refund count (>0)',
        'vnp_TransactionStatus' => '',
    ]))]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->status)->toBe(RefundStatus::REJECTED)
        ->and($result->retryable)->toBeFalse()
        ->and($result->providerAccepted())->toBeFalse();
});

test('refund maps provider error codes to domain statuses', function (string $responseCode, RefundStatus $status, bool $retryable): void {
    Http::fake(['*' => Http::response(vnpayRefundResponse([
        'vnp_ResponseCode' => $responseCode,
        'vnp_Message' => 'Error '.$responseCode,
        'vnp_TransactionStatus' => '',
    ]))]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->status)->toBe($status)
        ->and($result->retryable)->toBe($retryable)
        ->and($result->verified)->toBeTrue()
        ->and($result->providerAccepted())->toBeFalse();
})->with([
    ['91', RefundStatus::FAILED, false],
    ['95', RefundStatus::FAILED, false],
    ['09', RefundStatus::REJECTED, false],
    ['02', RefundStatus::NEEDS_REVIEW, false],
    ['03', RefundStatus::NEEDS_REVIEW, false],
    ['97', RefundStatus::NEEDS_REVIEW, false],
    ['99', RefundStatus::PROCESSING, true],
]);

test('refund maps a successful response with a failing transaction status to review', function (string $transactionStatus, RefundStatus $status): void {
    Http::fake(['*' => Http::response(vnpayRefundResponse([
        'vnp_TransactionStatus' => $transactionStatus,
    ]))]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->status)->toBe($status)
        ->and($result->retryable)->toBeFalse();
})->with([
    ['09', RefundStatus::REJECTED],
    ['02', RefundStatus::NEEDS_REVIEW],
    ['04', RefundStatus::NEEDS_REVIEW],
    ['07', RefundStatus::NEEDS_REVIEW],
]);

test('refund does not trust a response with an invalid checksum', function (): void {
    $response = vnpayRefundResponse();
    $response['vnp_SecureHash'] = 'deadbeef';

    Http::fake(['*' => Http::response($response)]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->verified)->toBeFalse()
        ->and($result->status)->toBe(RefundStatus::NEEDS_REVIEW)
        ->and($result->retryable)->toBeFalse()
        ->and($result->providerAccepted())->toBeFalse()
        ->and($result->error)->toBe('Invalid VNPay response checksum.');
});

test('refund marks a timeout as retryable transport failure', function (): void {
    Http::fake(function (): void {
        throw new ConnectionException('cURL error 28: Operation timed out after 15000 milliseconds');
    });

    $result = $this->client->refund(vnpayRefundData());

    expect($result->verified)->toBeFalse()
        ->and($result->retryable)->toBeTrue()
        ->and($result->responseCode)->toBeNull()
        ->and($result->error)->toContain('VNPay connection failed');
});

test('refund retries HTTP 5xx but not HTTP 4xx', function (int $status, bool $retryable, RefundStatus $outcome): void {
    Http::fake(['*' => Http::response('unavailable', $status)]);

    $result = $this->client->refund(vnpayRefundData());

    expect($result->verified)->toBeFalse()
        ->and($result->retryable)->toBe($retryable)
        ->and($result->status)->toBe($outcome);
})->with([
    [500, true, RefundStatus::PROCESSING],
    [503, true, RefundStatus::PROCESSING],
    [400, false, RefundStatus::NEEDS_REVIEW],
]);

test('refund rejects invalid amounts without calling VNPay', function (): void {
    Http::fake();

    expect(fn () => $this->client->refund(vnpayRefundData(['amount' => 0])))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

test('refund refuses to call VNPay when the feature flag is off', function (): void {
    config(['services.vnpay.refund_enabled' => false]);
    Http::fake();

    expect(fn () => $this->client->refund(vnpayRefundData()))
        ->toThrow(RuntimeException::class, 'VNPay refunds are disabled');

    Http::assertNothingSent();
});

test('querydr sends a signed request and verifies empty promotion fields', function (): void {
    Http::fake(['*' => Http::response(vnpayQueryResponse())]);

    $result = $this->client->queryDr(vnpayQueryData());

    expect($result->verified)->toBeTrue()
        ->and($result->found())->toBeTrue()
        ->and($result->responseCode)->toBe('00')
        ->and($result->transactionNo)->toBe('15713760')
        ->and($result->providerResponse)->not->toHaveKey('vnp_SecureHash');

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        expect($body['vnp_Command'])->toBe('querydr')
            ->and($body['vnp_TxnRef'])->toBe('100_20261024_ABCD')
            ->and($body['vnp_TransactionDate'])->toBe('20261024101010');

        $expected = vnpayRefundSign([
            $body['vnp_RequestId'],
            $body['vnp_Version'],
            $body['vnp_Command'],
            $body['vnp_TmnCode'],
            $body['vnp_TxnRef'],
            $body['vnp_TransactionDate'],
            $body['vnp_CreateDate'],
            $body['vnp_IpAddr'],
            $body['vnp_OrderInfo'],
        ]);

        expect($body['vnp_SecureHash'])->toBe($expected);

        return true;
    });
});

test('querydr rejects an invalid response checksum', function (): void {
    $response = vnpayQueryResponse();
    $response['vnp_SecureHash'] = 'deadbeef';

    Http::fake(['*' => Http::response($response)]);

    $result = $this->client->queryDr(vnpayQueryData());

    expect($result->verified)->toBeFalse()
        ->and($result->found())->toBeFalse()
        ->and($result->error)->toBe('Invalid VNPay response checksum.');
});

test('querydr reports transport failures without throwing', function (): void {
    Http::fake(function (): void {
        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    $result = $this->client->queryDr(vnpayQueryData());

    expect($result->verified)->toBeFalse()
        ->and($result->found())->toBeFalse()
        ->and($result->error)->toContain('VNPay connection failed');
});
