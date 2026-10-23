<?php

namespace App\DTOs;

readonly class CreateTourBookingData
{
    public function __construct(
        public int $customerId,
        public int $tourId,
        public int $slotId,
        public string $pricingMode,
        public int $durationValue,
        public ?string $couponCode = null,
        public ?string $meetingPoint = null,
        public ?string $customerNotes = null,
        public string $currency = 'VND',
    ) {}
}
