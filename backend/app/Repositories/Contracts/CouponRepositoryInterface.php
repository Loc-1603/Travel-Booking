<?php

namespace App\Repositories\Contracts;

use App\Models\Coupon;

interface CouponRepositoryInterface
{
    public function findActiveByCode(string $code): ?Coupon;

    /**
     * Load an active coupon by code with a row lock (use inside a transaction).
     */
    public function findActiveByCodeForUpdate(string $code): ?Coupon;

    /**
     * Total redemption count across hotel + tour bookings.
     * Excludes soft-deleted and cancelled bookings.
     *
     * When $forUpdate is true the count is a locking read inside the current
     * transaction, so it observes bookings committed after the transaction
     * snapshot was created (required for quota checks under REPEATABLE READ).
     */
    public function redemptionCount(Coupon $coupon, bool $forUpdate = false): int;

    /**
     * Redemption count for a specific user across hotel + tour bookings.
     * Excludes soft-deleted and cancelled bookings. Guest (null customer) bookings are not counted.
     */
    public function redemptionCountForUser(Coupon $coupon, int $userId, bool $forUpdate = false): int;
}
