<?php

namespace App\Repositories\Eloquent;

use App\Enums\TourSlotStatus;
use App\Models\TourAvailabilitySlot;
use App\Repositories\Contracts\TourAvailabilitySlotRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentTourAvailabilitySlotRepository implements TourAvailabilitySlotRepositoryInterface
{
    public function findById(int $id, array $with = []): ?TourAvailabilitySlot
    {
        return TourAvailabilitySlot::with($with)->find($id);
    }

    public function findByIdForUpdate(int $id): ?TourAvailabilitySlot
    {
        return TourAvailabilitySlot::where('id', $id)->lockForUpdate()->first();
    }

    public function findWithTourProvider(int $id): ?TourAvailabilitySlot
    {
        return TourAvailabilitySlot::with('tour.provider')->find($id);
    }

    public function findGuideSlot(string $providerUuid, ?string $travelDate = null): ?TourAvailabilitySlot
    {
        $query = TourAvailabilitySlot::query()
            ->with('tour.provider')
            ->join('tour_products', 'tour_products.id', '=', 'tour_availability_slots.tour_id')
            ->join('tour_providers', 'tour_providers.id', '=', 'tour_products.provider_id')
            ->where('tour_providers.uuid', $providerUuid)
            ->where('tour_providers.status', 'approved')
            ->where('tour_products.status', 'published')
            ->where('tour_availability_slots.status', 'available')
            ->select('tour_availability_slots.*');

        if ($travelDate !== null) {
            $query->whereDate('tour_availability_slots.date', '=', $travelDate);
        }

        return $query->orderBy('tour_availability_slots.start_time')->first();
    }

    public function listForTourRange(int $tourId, string $from, string $to): Collection
    {
        return TourAvailabilitySlot::where('tour_id', $tourId)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    public function update(TourAvailabilitySlot $slot, array $attributes): TourAvailabilitySlot
    {
        $slot->update($attributes);

        return $slot->fresh() ?? $slot;
    }

    public function releaseExpiredHolds(): int
    {
        return TourAvailabilitySlot::where('status', TourSlotStatus::HELD->value)
            ->whereNotNull('held_until')
            ->where('held_until', '<', Carbon::now())
            ->update(['status' => TourSlotStatus::AVAILABLE->value, 'held_until' => null, 'tour_booking_id' => null]);
    }
}
