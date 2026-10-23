<?php

namespace App\Actions;

use App\DTOs\CreateGuideTourBookingData;
use App\Models\TourBooking;
use App\Services\TourBookingService;

/**
 * Standard formula for creating a 1-day tour booking by guide (Phase 2 / A-2).
 */
class CreateGuideTourBookingAction
{
    public function __construct(
        protected TourBookingService $bookings,
    ) {}

    public function handle(CreateGuideTourBookingData $data): TourBooking
    {
        return $this->bookings->createGuideBooking(
            customerId: $data->customerId,
            providerUuid: $data->providerUuid,
            slotId: $data->slotId,
            travelDate: $data->travelDate,
            couponCode: $data->couponCode,
            meetingPoint: $data->meetingPoint,
            customerNotes: $data->customerNotes,
            currency: $data->currency,
        );
    }
}
