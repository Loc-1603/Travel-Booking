<?php

namespace App\Services;

use App\Enums\TourSlotStatus;
use App\Models\TourAvailabilitySlot;
use Illuminate\Support\Carbon;

/**
 * Slot-level availability for 1vs1 tours (capacity always = 1).
 * Mirrors AvailabilityService pessimistic-lock pattern, but per-slot
 * instead of per-night room counts. Hotel tables untouched.
 */
class TourAvailabilityService
{
    public function listSlots(int $tourId, string $from, string $to): \Illuminate\Database\Eloquent\Collection
    {
        return TourAvailabilitySlot::where('tour_id', $tourId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Lock a single slot for booking. Must be called inside a DB transaction.
     *
     * @throws \RuntimeException when slot is not bookable
     */
    public function lockSlot(int $slotId, ?int $expectedTourId = null): TourAvailabilitySlot
    {
        $slot = TourAvailabilitySlot::where('id', $slotId)->lockForUpdate()->first();
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
            $slot->update(['status' => TourSlotStatus::AVAILABLE->value, 'held_until' => null, 'tour_booking_id' => null]);
            $slot->refresh();
        }

        if ($slot->status !== TourSlotStatus::AVAILABLE->value) {
            throw new \RuntimeException('Tour slot is no longer available.');
        }

        $slot->update([
            'status' => TourSlotStatus::HELD->value,
            'held_until' => Carbon::now()->addMinutes((int) config('tour.hold_ttl_minutes', 15)),
        ]);

        return $slot->fresh();
    }

    public function confirmSlot(TourAvailabilitySlot $slot, int $bookingId): void
    {
        $slot->update([
            'status' => TourSlotStatus::BOOKED->value,
            'held_until' => null,
            'tour_booking_id' => $bookingId,
        ]);
    }

    public function releaseSlot(TourAvailabilitySlot $slot): void
    {
        $slot->update([
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
        return TourAvailabilitySlot::where('status', TourSlotStatus::HELD->value)
            ->whereNotNull('held_until')
            ->where('held_until', '<', Carbon::now())
            ->update(['status' => TourSlotStatus::AVAILABLE->value, 'held_until' => null, 'tour_booking_id' => null]);
    }
}
