<?php

namespace App\Services;

use App\Enums\TourSlotStatus;
use App\Repositories\Contracts\TourAvailabilitySlotRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * Slot-level availability for 1vs1 tours (capacity always = 1).
 */
class TourAvailabilityService
{
    public function __construct(
        protected TourAvailabilitySlotRepositoryInterface $slots,
    ) {}

    public function listSlots(int $tourId, string $from, string $to): \Illuminate\Database\Eloquent\Collection
    {
        return $this->slots->listForTourRange($tourId, $from, $to);
    }

    /**
     * Lock a single slot for booking. Must be called inside a DB transaction.
     *
     * @throws \RuntimeException when slot is not bookable
     */
    public function lockSlot(int $slotId, ?int $expectedTourId = null): \App\Models\TourAvailabilitySlot
    {
        $slot = $this->slots->findByIdForUpdate($slotId);
        if (! $slot) {
            throw new \RuntimeException('Tour slot not found.');
        }
        if ($expectedTourId !== null && (int) $slot->tour_id !== $expectedTourId) {
            throw new \RuntimeException('Slot does not belong to the selected tour.');
        }

        // Expire stale holds opportunistically.
        if ($slot->status === TourSlotStatus::HELD->value
            && $slot->held_until !== null
            && $slot->held_until->isPast()) {
            $slot = $this->slots->update($slot, ['status' => TourSlotStatus::AVAILABLE->value, 'held_until' => null, 'tour_booking_id' => null]);
        }

        if ($slot->status !== TourSlotStatus::AVAILABLE->value) {
            throw new \RuntimeException('Tour slot is no longer available.');
        }

        $slot = $this->slots->update($slot, [
            'status' => TourSlotStatus::HELD->value,
            'held_until' => Carbon::now()->addMinutes((int) config('tour.hold_ttl_minutes', 15)),
        ]);

        return $slot;
    }

    public function confirmSlot(\App\Models\TourAvailabilitySlot $slot, int $bookingId): void
    {
        $this->slots->update($slot, [
            'status' => TourSlotStatus::BOOKED->value,
            'held_until' => null,
            'tour_booking_id' => $bookingId,
        ]);
    }

    public function releaseSlot(\App\Models\TourAvailabilitySlot $slot): void
    {
        $this->slots->update($slot, [
            'status' => TourSlotStatus::AVAILABLE->value,
            'held_until' => null,
            'tour_booking_id' => null,
        ]);
    }

    /**
     * Release holds whose TTL expired (called by scheduler/job in Phase 8).
     */
    public function releaseExpiredHolds(): int
    {
        return $this->slots->releaseExpiredHolds();
    }
}
