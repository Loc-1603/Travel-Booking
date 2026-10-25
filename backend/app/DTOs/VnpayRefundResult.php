<?php

namespace App\DTOs;

use App\Enums\RefundStatus;

readonly class VnpayRefundResult
{
    /**
     * Response fields hashed by VNPay, in this exact order (no sorting).
     *
     * @var array<int, string>
     */
    public const CHECKSUM_FIELDS = [
        'vnp_ResponseId',
        'vnp_Command',
        'vnp_ResponseCode',
        'vnp_Message',
        'vnp_TmnCode',
        'vnp_TxnRef',
        'vnp_Amount',
        'vnp_BankCode',
        'vnp_PayDate',
        'vnp_TransactionNo',
        'vnp_TransactionType',
        'vnp_TransactionStatus',
        'vnp_OrderInfo',
    ];

    /**
     * @param  array<string, mixed>  $providerResponse
     */
    public function __construct(
        public bool $verified,
        public RefundStatus $status,
        public bool $retryable,
        public ?string $responseCode = null,
        public ?string $transactionStatus = null,
        public ?string $message = null,
        public ?string $responseId = null,
        public ?string $refundTransactionNo = null,
        public ?string $payDate = null,
        public ?string $bankCode = null,
        public ?string $txnRef = null,
        public ?int $amount = null,
        public array $providerResponse = [],
        public ?string $error = null,
    ) {}

    /**
     * VNPay accepted the refund (ResponseCode 00), so refunded_amount may be cached.
     * A replayed request (94 "duplicated") also proves the original request was accepted.
     */
    public function providerAccepted(): bool
    {
        if (! $this->verified) {
            return false;
        }

        if ($this->responseCode === '94') {
            return str_contains(strtolower((string) $this->message), 'duplicat');
        }

        return $this->responseCode === '00'
            && in_array($this->transactionStatus, ['00', '01', '05', '06'], true);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromProvider(array $response): self
    {
        $responseCode = self::stringValue($response['vnp_ResponseCode'] ?? null);
        $transactionStatus = self::stringValue($response['vnp_TransactionStatus'] ?? null);
        $message = self::stringValue($response['vnp_Message'] ?? null) ?? '';
        $refundTransactionNo = self::stringValue($response['vnp_TransactionNo'] ?? null);
        $amount = self::stringValue($response['vnp_Amount'] ?? null);

        [$status, $retryable] = self::mapOutcome($responseCode, $transactionStatus, $message);

        $error = null;
        if (in_array($status, [RefundStatus::FAILED, RefundStatus::REJECTED, RefundStatus::NEEDS_REVIEW], true)) {
            $error = $message !== '' ? $message : 'VNPay refund did not complete ('.$responseCode.').';
        }

        return new self(
            verified: true,
            status: $status,
            retryable: $retryable,
            responseCode: $responseCode,
            transactionStatus: $transactionStatus,
            message: $message !== '' ? $message : null,
            responseId: self::stringValue($response['vnp_ResponseId'] ?? null),
            refundTransactionNo: $refundTransactionNo,
            payDate: self::stringValue($response['vnp_PayDate'] ?? null),
            bankCode: self::stringValue($response['vnp_BankCode'] ?? null),
            txnRef: self::stringValue($response['vnp_TxnRef'] ?? null),
            amount: $amount !== null && is_numeric($amount) ? (int) round(((int) $amount) / 100) : null,
            providerResponse: self::redact($response),
            error: $error,
        );
    }

    /**
     * Response with an invalid checksum must not be trusted or retried blindly.
     */
    public static function unverified(array $response): self
    {
        $message = self::stringValue($response['vnp_Message'] ?? null);

        return new self(
            verified: false,
            status: RefundStatus::NEEDS_REVIEW,
            retryable: false,
            responseCode: self::stringValue($response['vnp_ResponseCode'] ?? null),
            transactionStatus: self::stringValue($response['vnp_TransactionStatus'] ?? null),
            message: $message !== '' ? $message : null,
            txnRef: self::stringValue($response['vnp_TxnRef'] ?? null),
            providerResponse: self::redact($response),
            error: 'Invalid VNPay response checksum.',
        );
    }

    /**
     * Network/timeout/HTTP failure before any trustworthy provider response.
     *
     * @param  array<string, mixed>  $context
     */
    public static function transportFailure(string $error, bool $retryable, array $context = []): self
    {
        return new self(
            verified: false,
            status: $retryable ? RefundStatus::PROCESSING : RefundStatus::NEEDS_REVIEW,
            retryable: $retryable,
            providerResponse: $context,
            error: $error,
        );
    }

    /**
     * @return array{0: RefundStatus, 1: bool}
     */
    private static function mapOutcome(?string $responseCode, ?string $transactionStatus, string $message): array
    {
        if ($responseCode === '00') {
            return match ($transactionStatus) {
                '00' => [RefundStatus::SUCCEEDED, false],
                '01', '05', '06' => [RefundStatus::PROCESSING, false],
                '09' => [RefundStatus::REJECTED, false],
                '02', '04', '07' => [RefundStatus::NEEDS_REVIEW, false],
                default => [RefundStatus::NEEDS_REVIEW, false],
            };
        }

        return match ($responseCode) {
            '94' => self::mapDuplicateOrLimit($message),
            '91', '95' => [RefundStatus::FAILED, false],
            '09' => [RefundStatus::REJECTED, false],
            '02', '03', '97' => [RefundStatus::NEEDS_REVIEW, false],
            '99' => [RefundStatus::PROCESSING, true],
            default => [RefundStatus::NEEDS_REVIEW, false],
        };
    }

    /**
     * "94" covers both a duplicated request (safe: still processing) and a
     * provider limit on a new obligation (sandbox/NCB: 1 refund per payment).
     *
     * @return array{0: RefundStatus, 1: bool}
     */
    private static function mapDuplicateOrLimit(string $message): array
    {
        $normalized = strtolower($message);

        if (str_contains($normalized, 'duplicat')) {
            return [RefundStatus::PROCESSING, false];
        }

        if (str_contains($normalized, 'too many refund')) {
            return [RefundStatus::REJECTED, false];
        }

        return [RefundStatus::NEEDS_REVIEW, false];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private static function redact(array $response): array
    {
        unset($response['vnp_SecureHash'], $response['vnp_SecureHashType']);

        return array_filter(
            $response,
            static fn (mixed $value): bool => is_scalar($value) || $value === null,
        );
    }

    private static function stringValue(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
