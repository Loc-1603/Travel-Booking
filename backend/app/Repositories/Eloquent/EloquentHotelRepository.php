<?php

namespace App\Repositories\Eloquent;

use App\Models\Hotel;
use App\Repositories\Contracts\HotelRepositoryInterface;

class EloquentHotelRepository implements HotelRepositoryInterface
{
    public function findById(int $id, array $with = []): ?Hotel
    {
        return Hotel::with($with)->find($id);
    }
}
