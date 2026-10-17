<?php

namespace App\Repositories\Contracts;

use App\Models\Hotel;

interface HotelRepositoryInterface
{
    public function findById(int $id, array $with = []): ?Hotel;
}
