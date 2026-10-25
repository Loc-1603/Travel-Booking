<?php

namespace App\Services;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;

class CouponService
{
    public function __construct(
        protected CouponRepositoryInterface $coupons,
    ) {}

    /**
     * Find an active coupon by code (read-only, or locked when requested).
     */
    public function findActiveByCode(string $code, bool $forUpdate = false): ?Coupon
    {
        if ($forUpdate) {
            return $this->coupons->findActiveByCodeForUpdate($code);
        }

        return $this->coupons->findActiveByCode($code);
    }

    /**
     * Find an active coupon by code with a row lock (use inside a transaction).
     */
    public function findActiveByCodeForUpdate(string $code): ?Coupon
    {
        return $this->coupons->findActiveByCodeForUpdate($code);
    }

    /**
     * Whether the coupon still has total/per-user redemption capacity.
     */
    public function passesUsageLimits(Coupon $coupon, ?int $userId, bool $forUpdate = false): bool
    {
        if ($coupon->usage_limit_total !== null) {
            if ($this->coupons->redemptionCount($coupon, $forUpdate) >= $coupon->usage_limit_total) {
                return false;
            }
        }
        if ($userId !== null && $coupon->usage_limit_per_user !== null) {
            if ($this->coupons->redemptionCountForUser($coupon, $userId, $forUpdate) >= $coupon->usage_limit_per_user) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate coupon and return discount amount for given context.
     * Returns [discountAmount, coupon] or [0, null] if invalid.
     *
     * @param  array<int, int>  $roomQuantities  room_id => quantity
     */
    public function validateAndGetDiscount(
        string $code,
        int $hotelId,
        array $roomQuantities,
        float $subtotal,
        string $checkIn,
        string $checkOut,
        int $nights,
        ?int $userId = null,
        bool $lockCoupon = false,
    ): array {
        $coupon = $this->findActiveByCode($code, $lockCoupon);
        if (! $coupon) {
            return [0.0, null];
        }

        if ($coupon->valid_from && $coupon->valid_from->isAfter($checkIn)) {
            return [0.0, null];
        }
        if ($coupon->valid_to && $coupon->valid_to->isBefore($checkOut)) {
            return [0.0, null];
        }

        if ($coupon->min_nights !== null && $nights < $coupon->min_nights) {
            return [0.0, null];
        }
        if ($coupon->min_amount !== null && $subtotal < (float) $coupon->min_amount) {
            return [0.0, null];
        }

        if ($coupon->hotel_ids !== null && count($coupon->hotel_ids) > 0 && ! in_array($hotelId, $coupon->hotel_ids, true)) {
            return [0.0, null];
        }

        $roomIds = array_keys($roomQuantities);
        if ($coupon->room_ids !== null && count($coupon->room_ids) > 0) {
            $allowed = array_intersect($roomIds, $coupon->room_ids);
            if (empty($allowed)) {
                return [0.0, null];
            }
        }

        if (! $this->passesUsageLimits($coupon, $userId, $lockCoupon)) {
            return [0.0, null];
        }

        $discount = $this->computeDiscount($coupon, $subtotal);

        return [$discount, $coupon];
    }

    /**
     * Validate a tour coupon and return discount amount for given context.
     * Returns [discountAmount, coupon] or [0, null] if invalid.
     */
    public function validateAndGetTourDiscount(
        string $code,
        float $subtotal,
        string $date,
        ?int $userId = null,
        bool $lockCoupon = false,
    ): array {
        $coupon = $this->findActiveByCode($code, $lockCoupon);
        if (! $coupon) {
            return [0.0, null];
        }

        if ($coupon->valid_from && $coupon->valid_from->isAfter($date)) {
            return [0.0, null];
        }
        if ($coupon->valid_to && $coupon->valid_to->isBefore($date)) {
            return [0.0, null];
        }

        if ($coupon->min_amount !== null && $subtotal < (float) $coupon->min_amount) {
            return [0.0, null];
        }

        if (! $this->passesUsageLimits($coupon, $userId, $lockCoupon)) {
            return [0.0, null];
        }

        $discount = $this->computeDiscount($coupon, $subtotal);

        return [$discount, $coupon];
    }

    public function computeDiscount(Coupon $coupon, float $subtotal): float
    {
        $value = (float) $coupon->value;
        if ($coupon->type === Coupon::TYPE_PERCENTAGE) {
            $value = min(100, max(0, $value));

            return round($subtotal * ($value / 100), 2);
        }

        return round(min($value, $subtotal), 2);
    }
}
