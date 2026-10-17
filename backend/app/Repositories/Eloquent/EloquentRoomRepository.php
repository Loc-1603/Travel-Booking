<?php

namespace App\Repositories\Eloquent;

use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;

class EloquentRoomRepository implements RoomRepositoryInterface
{
    public function findById(int $id): ?Room
    {
        return Room::find($id);
    }

    public function findOrFail(int $id): Room
    {
        return Room::findOrFail($id);
    }

    public function findInHotel(int $roomId, int $hotelId): ?Room
    {
        return Room::where('id', $roomId)->where('hotel_id', $hotelId)->first();
    }

    public function totalRoomsById(): array
    {
        return Room::pluck('total_rooms', 'id')->map(fn ($v) => (int) $v)->all();
    }
}
