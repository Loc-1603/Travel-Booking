<?php

namespace App\DTOs;

readonly class VnpayQueryData
{
    public function __construct(
        public string $requestId,
        public string $txnRef,
        public string $transactionDate,
        public string $orderInfo,
        public ?string $ipAddr = null,
        public ?string $createDate = null,
    ) {}
}
