<?php

namespace App\Repositories\Contracts;

use App\Models\TourDispute;

interface TourDisputeRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TourDispute;
}
