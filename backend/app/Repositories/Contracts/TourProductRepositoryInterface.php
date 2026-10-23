<?php

namespace App\Repositories\Contracts;

use App\Models\TourProduct;

interface TourProductRepositoryInterface
{
    public function findPublishedById(int $id): ?TourProduct;

    public function findByIdOrFail(int $id): TourProduct;

    public function loadProvinceCountry(TourProduct $tour): TourProduct;
}
