<?php

namespace App\Repositories\Eloquent;

use App\Models\TourProduct;
use App\Repositories\Contracts\TourProductRepositoryInterface;

class EloquentTourProductRepository implements TourProductRepositoryInterface
{
    public function findPublishedById(int $id): ?TourProduct
    {
        return TourProduct::where('id', $id)->where('status', 'published')->first();
    }

    public function findByIdOrFail(int $id): TourProduct
    {
        return TourProduct::findOrFail($id);
    }

    public function loadProvinceCountry(TourProduct $tour): TourProduct
    {
        $tour->loadMissing('province.country');

        return $tour;
    }
}
