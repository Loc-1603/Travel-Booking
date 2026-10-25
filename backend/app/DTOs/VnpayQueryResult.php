<?php

namespace App\DTOs;

readonly class VnpayQueryResult
{
    /**
     * Response fields hashed by VNPay for querydr, in this exact order.
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
        'vnp_PromotionCode',
        'vnp_PromotionAmount',
    ];

    /**
     * @param  array<string, mixed>  $providerResponse
     */
    public function __construct(
        public bool $verified,
        public ?string $responseCode = null,
        public ?string $transactionStatus = null,
        public ?string $message = null,
        public ?string $transactionNo = null,
        public ?string $payDate = null,
        public ?string $bankCode = null,
        public ?string $txnRef = null,
        public ?int $amount = null,
        public array $providerResponse = [],
        public ?string $error = null,
    ) {}

    /**
     * The original payment transaction was found (querydr never returns refunds).
     */
    public function found(): bool
    {
        return $this->verified && $this->responseCode === '00';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromProvider(array $response): self
    {
        $message = self::stringValue($response['vnp_Message'] ?? null);
        $amount = self::stringValue($response['vnp_Amount'] ?? null);

        return new self(
            verified: true,
            responseCode: self::stringValue($response['vnp_ResponseCode'] ?? null),
            transactionStatus: self::stringValue($response['vnp_TransactionStatus'] ?? null),
            message: $message,
            transactionNo: self::stringValue($response['vnp_TransactionNo'] ?? null),
            payDate: self::stringValue($response['vnp_PayDate'] ?? null),
            bankCode: self::stringValue($response['vnp_BankCode'] ?? null),
            txnRef: self::stringValue($response['vnp_TxnRef'] ?? null),
            amount: $amount !== null && is_numeric($amount) ? (int) round(((int) $amount) / 100) : null,
            providerResponse: self::redact($response),
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function unverified(array $response): self
    {
        return new self(
            verified: false,
            responseCode: self::stringValue($response['vnp_ResponseCode'] ?? null),
            transactionStatus: self::stringValue($response['vnp_TransactionStatus'] ?? null),
            message: self::stringValue($response['vnp_Message'] ?? null),
            txnRef: self::stringValue($response['vnp_TxnRef'] ?? null),
            providerResponse: self::redact($response),
            error: 'Invalid VNPay response checksum.',
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function transportFailure(string $error, array $context = []): self
    {
        return new self(
            verified: false,
            providerResponse: $context,
            error: $error,
        );
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
