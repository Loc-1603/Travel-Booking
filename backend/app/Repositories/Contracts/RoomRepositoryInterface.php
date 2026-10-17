<?php

namespace App\Repositories\Contracts;

use App\Models\Room;

interface RoomRepositoryInterface
{
    public function findById(int $id): ?Room;

    public function findOrFail(int $id): Room;

    public function findInHotel(int $roomId, int $hotelId): ?Room;

    /**
     * @return array<int, int> [room_id => total_rooms]
     */
    public function totalRoomsById(): array;
}
