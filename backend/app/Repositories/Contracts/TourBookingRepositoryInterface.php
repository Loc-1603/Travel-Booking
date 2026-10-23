<?php

namespace App\Repositories\Contracts;

use App\Models\TourBooking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface TourBookingRepositoryInterface
{
    public function findById(int $id, array $with = []): ?TourBooking;

    public function findByIdForUpdate(int $id): ?TourBooking;

    public function findByUuid(string $uuid, array $with = []): ?TourBooking;

    public function findByUuidOrFail(string $uuid, array $with = []): TourBooking;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TourBooking;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TourBooking $booking, array $attributes): TourBooking;

    public function paginateByCustomer(int $customerId, array $with = [], int $perPage = 15): LengthAwarePaginator;

    public function paginateForProviders(array $providerIds, ?string $status = null, ?string $from = null, ?string $to = null, int $perPage = 15): LengthAwarePaginator;

    public function expiredPendingIds(Carbon $cutoff): Collection;

    /**
     * @return array{ongoing: int, completed: int}
     */
    public function advanceTimedStatuses(?Carbon $now = null): array;
}
