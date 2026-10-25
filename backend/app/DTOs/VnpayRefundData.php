<?php

namespace App\DTOs;

use App\Enums\RefundType;

readonly class VnpayRefundData
{
    public function __construct(
        public string $requestId,
        public string $txnRef,
        public float $amount,
        public RefundType $type,
        public string $transactionDate,
        public string $orderInfo,
        public ?string $transactionNo = null,
        public ?string $createBy = null,
        public ?string $ipAddr = null,
        public ?string $createDate = null,
    ) {}
}
