<?php

namespace App\DTOs;

readonly class CreateBookingData
{
    /**
     * @param  array<int, int>  $roomQuantities  [room_id => quantity]
     */
    public function __construct(
        public ?int $customerId,
        public int $hotelId,
        public array $roomQuantities,
        public string $checkIn,
        public string $checkOut,
        public string $currency = 'VND',
        public ?string $couponCode = null,
        public ?string $guestEmail = null,
        public ?string $guestName = null,
        public bool $lateCheckout = false,
    ) {}
}
