<?php

namespace App\Actions;

use App\DTOs\CreateBookingData;
use App\Models\Booking;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Services\BookingService;

/**
 * Standard formula for creating a hotel booking (Phase 2 / A-1).
 *
 * Thin orchestration over BookingService (which still owns the
 * validate -> pricing -> lock -> create transaction until Phase 3
 * moves its queries to Repositories). This Action adds the one
 * validation the Service lacks: rooms must belong to the hotel
 * (currently duplicated in BookingController@preview/store and
 * moving here so controllers can go thin in Phase 4).
 */
class CreateBookingAction
{
    public function __construct(
        protected BookingService $bookings,
        protected RoomRepositoryInterface $rooms,
    ) {}

    public function handle(CreateBookingData $data): Booking
    {
        if ($data->roomQuantities === []) {
            throw new \InvalidArgumentException('At least one room is required.');
        }

        foreach ($data->roomQuantities as $roomId => $quantity) {
            if ($quantity < 1) {
                throw new \InvalidArgumentException("Invalid quantity for room {$roomId}.");
            }
            if ($this->rooms->findInHotel((int) $roomId, $data->hotelId) === null) {
                throw new \InvalidArgumentException('Room does not belong to the selected hotel.');
            }
        }

        return $this->bookings->createBooking(
            customerId: $data->customerId,
            hotelId: $data->hotelId,
            roomQuantities: $data->roomQuantities,
            checkIn: $data->checkIn,
            checkOut: $data->checkOut,
            currency: $data->currency,
            couponCode: $data->couponCode,
            guestEmail: $data->guestEmail,
            guestName: $data->guestName,
            lateCheckout: $data->lateCheckout,
        );
    }
}
