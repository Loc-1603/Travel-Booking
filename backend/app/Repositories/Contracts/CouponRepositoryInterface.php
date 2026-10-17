<?php

namespace App\Repositories\Contracts;

use App\Models\Coupon;

interface CouponRepositoryInterface
{
    public function findActiveByCode(string $code): ?Coupon;

    public function redemptionCount(Coupon $coupon): int;

    public function redemptionCountForUser(Coupon $coupon, int $userId): int;
}
