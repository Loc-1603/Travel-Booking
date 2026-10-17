<?php

namespace App\Repositories\Eloquent;

use App\Models\RoomAvailability;
use App\Repositories\Contracts\RoomAvailabilityRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentRoomAvailabilityRepository implements RoomAvailabilityRepositoryInterface
{
    public function findForDate(int $roomId, string $date): ?RoomAvailability
    {
        return RoomAvailability::where('room_id', $roomId)
            ->whereDate('date', $date)
            ->first();
    }

    public function findForDateLocked(int $roomId, string $date): ?RoomAvailability
    {
        return RoomAvailability::where('room_id', $roomId)
            ->whereDate('date', $date)
            ->lockForUpdate()
            ->first();
    }

    public function listForRange(int $roomId, string $checkIn, string $checkOut): Collection
    {
        return RoomAvailability::where('room_id', $roomId)
            ->whereDate('date', '>=', $checkIn)
            ->whereDate('date', '<=', $checkOut)
            ->get();
    }

    public function listForDateRange(string $from, string $to): Collection
    {
        return RoomAvailability::whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();
    }

    public function mapForRangeByDate(int $roomId, string $checkIn, string $checkOut): array
    {
        return $this->listForRange($roomId, $checkIn, $checkOut)
            ->keyBy(fn (RoomAvailability $row) => $row->date->format('Y-m-d'))
            ->all();
    }

    public function firstOrCreateForDate(int $roomId, string $date, array $values): RoomAvailability
    {
        // whereDate (not exact-match firstOrCreate): the date cast serializes
        // with a time part on SQLite, so an exact `date = ?` lookup would miss
        // the existing row and hit the unique constraint on re-ensure.
        $existing = RoomAvailability::where('room_id', $roomId)
            ->whereDate('date', $date)
            ->first();

        return $existing ?? RoomAvailability::create(
            array_merge(['room_id' => $roomId, 'date' => $date], $values)
        );
    }

    public function incrementAvailable(int $roomId, string $date, int $quantity): int
    {
        return RoomAvailability::where('room_id', $roomId)
            ->whereDate('date', $date)
            ->increment('available_rooms', $quantity);
    }
}
