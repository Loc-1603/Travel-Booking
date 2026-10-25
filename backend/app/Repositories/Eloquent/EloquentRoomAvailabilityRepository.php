<?php

namespace App\Repositories\Eloquent;

use App\Models\RoomAvailability;
use App\Repositories\Contracts\RoomAvailabilityRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentRoomAvailabilityRepository implements RoomAvailabilityRepositoryInterface
{
    public function findForDate(int $roomId, string $date): ?RoomAvailability
    {
        return $this->forDate($roomId, $date)->first();
    }

    public function findForDateLocked(int $roomId, string $date): ?RoomAvailability
    {
        return $this->forDate($roomId, $date)->lockForUpdate()->first();
    }

    public function listForRange(int $roomId, string $checkIn, string $checkOut): Collection
    {
        return RoomAvailability::where('room_id', $roomId)
            ->where('date', '>=', $checkIn)
            ->where('date', '<', $this->nextDay($checkOut))
            ->get();
    }

    public function listForDateRange(string $from, string $to): Collection
    {
        return RoomAvailability::where('date', '>=', $from)
            ->where('date', '<', $this->nextDay($to))
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
        // date-range match (not whereDate/exact equality): the date cast
        // serializes with a time part on SQLite, and a sargable range keeps
        // MySQL/PgSQL on the (room_id, date) index when locking.
        $existing = $this->forDate($roomId, $date)->first();

        return $existing ?? RoomAvailability::create(
            array_merge(['room_id' => $roomId, 'date' => $date], $values)
        );
    }

    public function incrementAvailable(int $roomId, string $date, int $quantity): int
    {
        return $this->forDate($roomId, $date)->increment('available_rooms', $quantity);
    }

    private function forDate(int $roomId, string $date): Builder
    {
        return RoomAvailability::where('room_id', $roomId)
            ->where('date', '>=', $date)
            ->where('date', '<', $this->nextDay($date));
    }

    private function nextDay(string $date): string
    {
        return Carbon::parse($date)->addDay()->toDateString();
    }
}
