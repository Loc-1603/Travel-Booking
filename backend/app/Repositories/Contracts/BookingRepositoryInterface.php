<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingRepositoryInterface
{
    public function findById(int $id, array $with = []): ?Booking;

    public function findByUuid(string $uuid, array $with = []): ?Booking;

    public function findByUuidOrFail(string $uuid, array $with = []): Booking;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Booking;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Booking $booking, array $attributes): Booking;

    /**
     * @param  array<string>  $with
     */
    public function paginateByCustomer(int $customerId, array $with = [], int $perPage = 15): LengthAwarePaginator;
}
