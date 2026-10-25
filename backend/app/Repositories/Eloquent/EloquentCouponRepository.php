<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Enums\TourBookingStatus;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\TourBooking;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class EloquentCouponRepository implements CouponRepositoryInterface
{
    public function findActiveByCode(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper($code))->where('is_active', true)->first();
    }

    public function findActiveByCodeForUpdate(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper($code))
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();
    }

    public function redemptionCount(Coupon $coupon, bool $forUpdate = false): int
    {
        $hotelCount = $this->countRows(
            Booking::where('coupon_id', $coupon->id)
                ->where('status', '!=', BookingStatus::CANCELLED->value),
            $forUpdate,
        );

        $tourCount = $this->countRows(
            TourBooking::where('coupon_id', $coupon->id)
                ->where('status', '!=', TourBookingStatus::CANCELLED->value),
            $forUpdate,
        );

        return $hotelCount + $tourCount;
    }

    public function redemptionCountForUser(Coupon $coupon, int $userId, bool $forUpdate = false): int
    {
        $hotelCount = $this->countRows(
            Booking::where('coupon_id', $coupon->id)
                ->where('customer_id', $userId)
                ->where('status', '!=', BookingStatus::CANCELLED->value),
            $forUpdate,
        );

        $tourCount = $this->countRows(
            TourBooking::where('coupon_id', $coupon->id)
                ->where('customer_id', $userId)
                ->where('status', '!=', TourBookingStatus::CANCELLED->value),
            $forUpdate,
        );

        return $hotelCount + $tourCount;
    }

    /**
     * Aggregate count; when locked, use a current read so the quota check sees
     * rows committed after this transaction's REPEATABLE READ snapshot.
     */
    private function countRows(Builder $query, bool $forUpdate): int
    {
        if (! $forUpdate) {
            return (int) $query->count();
        }

        // MySQL supports aggregate locks; PostgreSQL rejects FOR UPDATE with
        // aggregates, so lock the matching ids there and count in PHP.
        if ($query->getQuery()->getConnection()->getDriverName() === 'mysql') {
            return (int) $query->lockForUpdate()->count();
        }

        return (int) $query->lockForUpdate()->pluck('id')->count();
    }
}
