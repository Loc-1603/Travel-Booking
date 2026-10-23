<?php

namespace App\Repositories\Contracts;

use App\Models\TourAvailabilitySlot;
use Illuminate\Support\Collection;

interface TourAvailabilitySlotRepositoryInterface
{
    public function findById(int $id, array $with = []): ?TourAvailabilitySlot;

    public function findByIdForUpdate(int $id): ?TourAvailabilitySlot;

    public function findWithTourProvider(int $id): ?TourAvailabilitySlot;

    public function findGuideSlot(string $providerUuid, ?string $travelDate = null): ?TourAvailabilitySlot;

    public function listForTourRange(int $tourId, string $from, string $to): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TourAvailabilitySlot $slot, array $attributes): TourAvailabilitySlot;

    public function releaseExpiredHolds(): int;
}
