<?php

namespace App\DTOs;

readonly class TourCheckoutData
{
    public function __construct(
        public int $bookingId,
        public ?string $ipAddr = null,
        public ?string $bankCode = null,
    ) {}
}
