<?php

namespace App\DTOs;

readonly class CheckoutData
{
    public function __construct(
        public int $bookingId,
        public ?string $ipAddr = null,
        public ?string $bankCode = null,
    ) {}
}
