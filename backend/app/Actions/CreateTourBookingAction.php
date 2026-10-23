<?php

namespace App\Actions;

use App\DTOs\CreateTourBookingData;
use App\Models\TourBooking;
use App\Repositories\Contracts\TourAvailabilitySlotRepositoryInterface;
use App\Repositories\Contracts\TourProductRepositoryInterface;
use App\Services\TourBookingService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Standard formula for creating a tour booking (Phase 2 / A-1).
 */
class CreateTourBookingAction
{
    public function __construct(
        protected TourBookingService $bookings,
        protected TourProductRepositoryInterface $products,
        protected TourAvailabilitySlotRepositoryInterface $slots,
    ) {}

    public function handle(CreateTourBookingData $data): TourBooking
    {
        $tour = $this->products->findPublishedById($data->tourId);
        if (! $tour) {
            throw new RuntimeException('Tour is not available for booking.');
        }

        $slot = $this->slots->findById($data->slotId);
        if (! $slot || (int) $slot->tour_id !== $data->tourId) {
            throw new InvalidArgumentException('Slot does not belong to the selected tour.');
        }

        return $this->bookings->createBooking(
            customerId: $data->customerId,
            tourId: $data->tourId,
            slotId: $data->slotId,
            pricingMode: $data->pricingMode,
            durationValue: $data->durationValue,
            couponCode: $data->couponCode,
            meetingPoint: $data->meetingPoint,
            customerNotes: $data->customerNotes,
            currency: $data->currency,
        );
    }
}
