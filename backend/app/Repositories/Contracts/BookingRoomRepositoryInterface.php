<?php

namespace App\Repositories\Contracts;

use App\Models\BookingRoom;

interface BookingRoomRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): BookingRoom;
}
