<?php

namespace App\Repositories\Eloquent;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;

class EloquentCouponRepository implements CouponRepositoryInterface
{
    public function findActiveByCode(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper($code))->where('is_active', true)->first();
    }

    public function redemptionCount(Coupon $coupon): int
    {
        return $coupon->bookings()->count();
    }

    public function redemptionCountForUser(Coupon $coupon, int $userId): int
    {
        return $coupon->bookings()->where('customer_id', $userId)->count();
    }
}
