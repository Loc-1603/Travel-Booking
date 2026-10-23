<?php

namespace App\Repositories\Eloquent;

use App\Models\TourProvider;
use App\Repositories\Contracts\TourProviderRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentTourProviderRepository implements TourProviderRepositoryInterface
{
    public function providerIdsForVendor(int $vendorId): Collection
    {
        return TourProvider::where('vendor_id', $vendorId)->pluck('id');
    }
}
