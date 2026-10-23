<?php

namespace App\DTOs;

readonly class CreateGuideTourBookingData
{
    public function __construct(
        public int $customerId,
        public string $providerUuid,
        public ?int $slotId = null,
        public ?string $travelDate = null,
        public ?string $couponCode = null,
        public ?string $meetingPoint = null,
        public ?string $customerNotes = null,
        public string $currency = 'VND',
    ) {}
}
