<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface TourProviderRepositoryInterface
{
    public function providerIdsForVendor(int $vendorId): Collection;
}
