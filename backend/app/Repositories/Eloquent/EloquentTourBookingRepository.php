<?php

namespace App\Repositories\Eloquent;

use App\Enums\TourBookingStatus;
use App\Models\TourBooking;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentTourBookingRepository implements TourBookingRepositoryInterface
{
    public function findById(int $id, array $with = []): ?TourBooking
    {
        return TourBooking::with($with)->find($id);
    }

    public function findByIdForUpdate(int $id): ?TourBooking
    {
        return TourBooking::where('id', $id)->lockForUpdate()->first();
    }

    public function findByUuid(string $uuid, array $with = []): ?TourBooking
    {
        return TourBooking::where('uuid', $uuid)->with($with)->first();
    }

    public function findByUuidOrFail(string $uuid, array $with = []): TourBooking
    {
        return TourBooking::where('uuid', $uuid)->with($with)->firstOrFail();
    }

    public function create(array $attributes): TourBooking
    {
        return TourBooking::create($attributes);
    }

    public function update(TourBooking $booking, array $attributes): TourBooking
    {
        $booking->update($attributes);

        return $booking->fresh() ?? $booking;
    }

    public function paginateByCustomer(int $customerId, array $with = [], int $perPage = 15): LengthAwarePaginator
    {
        return TourBooking::where('customer_id', $customerId)
            ->with($with)
            ->withCount('review')
            ->orderByDesc('start_at')
            ->latest()
            ->paginate($perPage);
    }

    public function paginateForProviders(array $providerIds, ?string $status = null, ?string $from = null, ?string $to = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = TourBooking::whereIn('provider_id', $providerIds)
            ->with(['tour', 'slot', 'customer']);

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }
        if ($from !== null && $from !== '') {
            $query->whereDate('start_at', '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $query->whereDate('start_at', '<=', $to);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function expiredPendingIds(Carbon $cutoff): Collection
    {
        return TourBooking::where('status', TourBookingStatus::PENDING_PAYMENT->value)
            ->where('created_at', '<=', $cutoff)
            ->pluck('id');
    }

    public function advanceTimedStatuses(?Carbon $now = null): array
    {
        $now = $now ?? now();

        $ongoing = TourBooking::where('status', TourBookingStatus::CONFIRMED->value)
            ->where('start_at', '<=', $now)
            ->update(['status' => TourBookingStatus::ONGOING->value]);

        $completed = TourBooking::where('status', TourBookingStatus::ONGOING->value)
            ->where('end_at', '<=', $now)
            ->update(['status' => TourBookingStatus::COMPLETED->value]);

        return ['ongoing' => $ongoing, 'completed' => $completed];
    }
}
