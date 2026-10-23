<?php

namespace App\Repositories\Eloquent;

use App\Models\TourDispute;
use App\Repositories\Contracts\TourDisputeRepositoryInterface;

class EloquentTourDisputeRepository implements TourDisputeRepositoryInterface
{
    public function create(array $attributes): TourDispute
    {
        return TourDispute::create($attributes);
    }
}
