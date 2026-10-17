<?php

namespace App\Repositories\Eloquent;

use App\Models\BookingRoom;
use App\Repositories\Contracts\BookingRoomRepositoryInterface;

class EloquentBookingRoomRepository implements BookingRoomRepositoryInterface
{
    public function create(array $attributes): BookingRoom
    {
        return BookingRoom::create($attributes);
    }
}
