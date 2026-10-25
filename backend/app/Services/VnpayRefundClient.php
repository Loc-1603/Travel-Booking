<?php

namespace App\Services;

use App\DTOs\VnpayQueryData;
use App\DTOs\VnpayQueryResult;
use App\DTOs\VnpayRefundData;
use App\DTOs\VnpayRefundResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * VNPay Merchant Web API client for refunds and transaction queries.
 *
 * Request bodies are JSON and checksums are HMAC-SHA512 over pipe-separated
 * values in a fixed order (NOT sorted, unlike the payment URL signature).
 *
 * @see https://sandbox.vnpayment.vn/apis/docs/truy-van-hoan-tien/
 */
class VnpayRefundClient
{
    public const DEFAULT_API_URL = 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction';

    /**
     * Request checksum fields for `refund`, in this exact order.
     *
     * @var array<int, string>
     */
    public const REFUND_REQUEST_FIELDS = [
        'RequestId',
        'Version',
        'Command',
        'TmnCode',
        'TransactionType',
        'TxnRef',
        'Amount',
        'TransactionNo',
        'TransactionDate',
        'CreateBy',
        'CreateDate',
        'IpAddr',
        'OrderInfo',
    ];

    /**
     * Request checksum fields for `querydr`, in this exact order.
     *
     * @var array<int, string>
     */
    public const QUERY_REQUEST_FIELDS = [
        'RequestId',
        'Version',
        'Command',
        'TmnCode',
        'TxnRef',
        'TransactionDate',
        'CreateDate',
        'IpAddr',
        'OrderInfo',
    ];

    public function isConfigured(): bool
    {
        return $this->tmnCode() !== '' && $this->hashSecret() !== '';
    }

    public function enabled(): bool
    {
        return (bool) config('services.vnpay.refund_enabled', false);
    }

    /**
     * Send a refund request. The caller owns persistence and retry decisions.
     */
    public function refund(VnpayRefundData $data): VnpayRefundResult
    {
        $this->assertEnabled();
        $this->assertConfigured();

        $amount = $this->amountInProviderUnits($data->amount);
        $this->assertRequestData($data->requestId, $data->txnRef, $data->transactionDate, $data->orderInfo);

        $createDate = $data->createDate ?: now('Asia/Ho_Chi_Minh')->format('YmdHis');
        $createBy = $data->createBy ?: (string) config('services.vnpay.refund_create_by', 'system');
        $ipAddr = $data->ipAddr ?: $this->resolveIpAddr();

        $fields = [
            'RequestId' => $data->requestId,
            'Version' => $this->version(),
            'Command' => 'refund',
            'TmnCode' => $this->tmnCode(),
            'TransactionType' => $data->type->value,
            'TxnRef' => $data->txnRef,
            'Amount' => $amount,
            'TransactionNo' => (string) ($data->transactionNo ?? ''),
            'TransactionDate' => $data->transactionDate,
            'CreateBy' => $createBy,
            'CreateDate' => $createDate,
            'IpAddr' => $ipAddr,
            'OrderInfo' => $data->orderInfo,
        ];

        $body = $this->toBody($fields);
        $body['vnp_SecureHash'] = $this->signature($fields);

        [$payload, $error, $retryable] = $this->send($body);

        if ($payload === null) {
            return VnpayRefundResult::transportFailure(
                $error ?? 'VNPay refund request failed.',
                $retryable,
                ['request_id' => $data->requestId, 'txn_ref' => $data->txnRef],
            );
        }

        if (! $this->verifyResponse($payload, VnpayRefundResult::CHECKSUM_FIELDS)) {
            return VnpayRefundResult::unverified($payload);
        }

        return VnpayRefundResult::fromProvider($payload);
    }

    /**
     * Query the original payment transaction (querydr does not return refunds).
     */
    public function queryDr(VnpayQueryData $data): VnpayQueryResult
    {
        $this->assertConfigured();

        $this->assertRequestData($data->requestId, $data->txnRef, $data->transactionDate, $data->orderInfo);

        $createDate = $data->createDate ?: now('Asia/Ho_Chi_Minh')->format('YmdHis');
        $ipAddr = $data->ipAddr ?: $this->resolveIpAddr();

        $fields = [
            'RequestId' => $data->requestId,
            'Version' => $this->version(),
            'Command' => 'querydr',
            'TmnCode' => $this->tmnCode(),
            'TxnRef' => $data->txnRef,
            'TransactionDate' => $data->transactionDate,
            'CreateDate' => $createDate,
            'IpAddr' => $ipAddr,
            'OrderInfo' => $data->orderInfo,
        ];

        $body = $this->toBody($fields);
        $body['vnp_SecureHash'] = $this->signature($fields);

        [$payload, $error] = $this->send($body);

        if ($payload === null) {
            return VnpayQueryResult::transportFailure(
                $error ?? 'VNPay query request failed.',
                ['request_id' => $data->requestId, 'txn_ref' => $data->txnRef],
            );
        }

        if (! $this->verifyResponse($payload, VnpayQueryResult::CHECKSUM_FIELDS)) {
            return VnpayQueryResult::unverified($payload);
        }

        return VnpayQueryResult::fromProvider($payload);
    }

    /**
     * @param  array<int, string>  $fields  response keys in VNPay checksum order
     * @param  array<string, mixed>  $response
     */
    protected function verifyResponse(array $response, array $fields): bool
    {
        $received = (string) ($response['vnp_SecureHash'] ?? '');
        if ($received === '') {
            return false;
        }

        $values = [];
        foreach ($fields as $field) {
            $values[] = $this->normalize($response[$field] ?? null);
        }

        return hash_equals(strtolower($this->signature($values)), strtolower($received));
    }

    /**
     * HMAC-SHA512 over pipe-separated values in the given (unsorted) order.
     *
     * @param  array<array-key, mixed>  $values
     */
    protected function signature(array $values): string
    {
        $normalized = array_map(fn (mixed $value): string => $this->normalize($value), $values);

        return hash_hmac('sha512', implode('|', $normalized), $this->hashSecret());
    }

    /**
     * POST JSON and normalize transport outcomes.
     *
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>|null, 1: ?string, 2: bool}
     */
    private function send(array $body): array
    {
        $url = (string) config('services.vnpay.api_url', self::DEFAULT_API_URL);
        $timeout = (int) config('services.vnpay.refund_timeout', 15);
        if ($timeout <= 0) {
            $timeout = 15;
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($url, $body);
        } catch (ConnectionException $e) {
            return [null, Str::limit('VNPay connection failed: '.$e->getMessage(), 500), true];
        } catch (Throwable $e) {
            return [null, Str::limit('VNPay request failed: '.$e->getMessage(), 500), true];
        }

        if ($response->serverError()) {
            return [null, 'VNPay server error (HTTP '.$response->status().').', true];
        }

        if (! $response->successful()) {
            return [null, 'VNPay request rejected (HTTP '.$response->status().').', false];
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return [null, 'VNPay returned an invalid response.', false];
        }

        return [$payload, null, false];
    }

    /**
     * @param  array<string, string|int>  $fields
     * @return array<string, string|int>
     */
    private function toBody(array $fields): array
    {
        $body = [];
        foreach ($fields as $key => $value) {
            $body['vnp_'.$key] = $value;
        }

        return $body;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertRequestData(string $requestId, string $txnRef, string $transactionDate, string $orderInfo): void
    {
        if (trim($requestId) === '' || trim($txnRef) === '') {
            throw new \InvalidArgumentException('requestId and txnRef are required for VNPay refunds.');
        }

        if (preg_match('/^\d{14}$/', $transactionDate) !== 1) {
            throw new \InvalidArgumentException('transactionDate must be in VNPay format yyyyMMddHHmmss.');
        }

        if (trim($orderInfo) === '') {
            throw new \InvalidArgumentException('orderInfo is required for VNPay refunds.');
        }
    }

    /**
     * VNPay always expects the amount multiplied by 100.
     */
    private function amountInProviderUnits(float $amount): int
    {
        $providerAmount = (int) round($amount * 100);

        if ($providerAmount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        return $providerAmount;
    }

    private function resolveIpAddr(): string
    {
        $ip = request()->ip();

        return $ip !== null && $ip !== '' ? $ip : '127.0.0.1';
    }

    private function normalize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function version(): string
    {
        return (string) config('services.vnpay.version', '2.1.0');
    }

    private function tmnCode(): string
    {
        return (string) config('services.vnpay.tmn_code', '');
    }

    private function hashSecret(): string
    {
        return (string) config('services.vnpay.hash_secret', '');
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('VNPay is not configured. Set VNPAY_TMN_CODE and VNPAY_HASH_SECRET in .env.');
        }
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('VNPay refunds are disabled. Set VNPAY_REFUND_ENABLED=true to enable.');
        }
    }
}
