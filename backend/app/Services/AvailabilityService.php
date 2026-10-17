<?php

namespace App\Services;

use App\Repositories\Contracts\RoomAvailabilityRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    public function __construct(
        protected RoomRepositoryInterface $rooms,
        protected RoomAvailabilityRepositoryInterface $availability,
    ) {}

    /**
     * Get minimum available room count for a room over a date range.
     * Uses room_availability; missing dates fall back to room.total_rooms.
     */
    public function getMinimumAvailability(int $roomId, string $checkIn, string $checkOut): int
    {
        $room = $this->rooms->findOrFail($roomId);
        $period = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();
        $min = PHP_INT_MAX;

        foreach ($period as $date) {
            $av = $this->availability->findForDate($roomId, $date->format('Y-m-d'));
            $min = min($min, $av ? (int) $av->available_rooms : $room->total_rooms);
        }

        return $min === PHP_INT_MAX ? 0 : $min;
    }

    /**
     * Get availability per date for a room in range (batch query).
     */
    public function getAvailabilityForRange(int $roomId, string $checkIn, string $checkOut): array
    {
        $room = $this->rooms->findOrFail($roomId);
        $rows = $this->availability->mapForRangeByDate($roomId, $checkIn, $checkOut);

        $result = [];
        $period = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $result[$key] = isset($rows[$key]) ? (int) $rows[$key]->available_rooms : $room->total_rooms;
        }

        return $result;
    }

    /**
     * Ensure room_availability rows exist for room for each date in range (default to total_rooms).
     */
    public function ensureAvailabilityRows(int $roomId, string $checkIn, string $checkOut): void
    {
        $room = $this->rooms->findOrFail($roomId);
        $period = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();

        foreach ($period as $date) {
            $this->availability->firstOrCreateForDate(
                $roomId,
                $date->format('Y-m-d'),
                ['available_rooms' => $room->total_rooms]
            );
        }
    }

    /**
     * Lock inventory: decrement available_rooms for (room_id, date) for each date in range.
     * Uses pessimistic locking. Call within a DB transaction.
     */
    public function lockInventory(int $roomId, int $quantity, string $checkIn, string $checkOut): void
    {
        $period = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();
        foreach ($period as $date) {
            $day = $date->format('Y-m-d');
            $av = $this->availability->findForDateLocked($roomId, $day);
            if (! $av) {
                throw new \RuntimeException("No availability row for room {$roomId} on {$day}. Call ensureAvailabilityRows first.");
            }
            if ($av->available_rooms < $quantity) {
                throw new \RuntimeException("Insufficient availability for room {$roomId} on {$day}.");
            }
            $av->decrement('available_rooms', $quantity);
        }
    }

    /**
     * Release lock: increment available_rooms (compensating action on failure or cancellation).
     */
    public function releaseInventory(int $roomId, int $quantity, string $checkIn, string $checkOut): void
    {
        $period = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();
        foreach ($period as $date) {
            $this->availability->incrementAvailable($roomId, $date->format('Y-m-d'), $quantity);
        }
    }

    /**
     * Room IDs that have at least minQuantity available for the entire date range (batch query).
     *
     * @return array<int>
     */
    public function getRoomIdsWithAvailability(string $checkIn, string $checkOut, int $minQuantity = 1): array
    {
        $period = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();
        $dates = array_map(fn ($d) => $d->format('Y-m-d'), iterator_to_array($period));
        if ($dates === []) {
            return [];
        }

        $rows = $this->availability->listForDateRange(min($dates), max($dates))->groupBy('room_id');

        $roomTotals = $this->rooms->totalRoomsById();
        $result = [];
        foreach ($roomTotals as $roomId => $totalRooms) {
            $avail = $rows->get($roomId);
            $min = PHP_INT_MAX;
            foreach ($dates as $date) {
                $r = $avail ? $avail->firstWhere(fn ($a) => $a->date->format('Y-m-d') === $date) : null;
                $min = min($min, $r ? (int) $r->available_rooms : (int) $totalRooms);
            }
            if ($min !== PHP_INT_MAX && $min >= $minQuantity) {
                $result[] = $roomId;
            }
        }

        return $result;
    }
}
