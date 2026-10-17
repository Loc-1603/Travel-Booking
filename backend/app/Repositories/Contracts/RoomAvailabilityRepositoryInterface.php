<?php

namespace App\Repositories\Contracts;

use App\Models\RoomAvailability;
use Illuminate\Support\Collection;

interface RoomAvailabilityRepositoryInterface
{
    public function findForDate(int $roomId, string $date): ?RoomAvailability;

    public function findForDateLocked(int $roomId, string $date): ?RoomAvailability;

    /**
     * @return Collection<int, RoomAvailability>
     */
    public function listForRange(int $roomId, string $checkIn, string $checkOut): Collection;

    /**
     * @return Collection<int, RoomAvailability>
     */
    public function listForDateRange(string $from, string $to): Collection;

    /**
     * Availability rows for a room in range, keyed by Y-m-d.
     *
     * @return array<string, RoomAvailability>
     */
    public function mapForRangeByDate(int $roomId, string $checkIn, string $checkOut): array;

    /**
     * @param  array<string, mixed>  $values
     */
    public function firstOrCreateForDate(int $roomId, string $date, array $values): RoomAvailability;

    public function incrementAvailable(int $roomId, string $date, int $quantity): int;
}
