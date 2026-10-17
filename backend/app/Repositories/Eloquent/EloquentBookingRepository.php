<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentBookingRepository implements BookingRepositoryInterface
{
    public function findById(int $id, array $with = []): ?Booking
    {
        return Booking::with($with)->find($id);
    }

    public function findByUuid(string $uuid, array $with = []): ?Booking
    {
        return Booking::where('uuid', $uuid)->with($with)->first();
    }

    public function findByUuidOrFail(string $uuid, array $with = []): Booking
    {
        return Booking::where('uuid', $uuid)->with($with)->firstOrFail();
    }

    public function create(array $attributes): Booking
    {
        return Booking::create($attributes);
    }

    public function update(Booking $booking, array $attributes): Booking
    {
        $booking->update($attributes);

        return $booking->fresh() ?? $booking;
    }

    public function paginateByCustomer(int $customerId, array $with = [], int $perPage = 15): LengthAwarePaginator
    {
        return Booking::where('customer_id', $customerId)
            ->with($with)
            ->latest()
            ->paginate($perPage);
    }
}
