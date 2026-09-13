<?php

namespace App\Services;

use App\DTOs\PriceBreakdown;
use App\Enums\TourPricingMode;
use App\Models\Coupon;
use App\Models\TourAvailabilitySlot;
use App\Models\TourProduct;

/**
 * Tour pricing: total = base_fixed + unit_price * duration + transport_fee
 *   - volume discount (>=8h 5% / >=3 days 5%, config tour.php)
 *   - generic coupon (active + date + min_amount + usage limits; hotel/room scoping ignored)
 *   - tax mirrored from PricingService (country tax_rate fallback).
 */
class TourPricingService
{
    public function __construct(
        protected CouponService $coupons,
    ) {}

    public function calculate(
        TourProduct $tour,
        ?TourAvailabilitySlot $slot,
        string $pricingMode,
        int $durationValue,
        ?string $couponCode = null,
        ?int $userId = null,
    ): PriceBreakdown {
        $mode = $pricingMode === TourPricingMode::DAY->value ? TourPricingMode::DAY : TourPricingMode::HOUR;

        $baseFixed = (float) $tour->base_fixed;
        $unitPrice = $mode === TourPricingMode::DAY
            ? (float) $tour->base_price_daily
            : (float) $tour->base_price_hourly;

        // Slot-level weekend/holiday override replaces the unit price.
        if ($slot?->price_override !== null) {
            $unitPrice = (float) $slot->price_override;
        }

        $transportFee = (float) ($tour->transport_fee ?? 0);
        $subtotal = $baseFixed + $unitPrice * $durationValue;

        $discount = $this->volumeDiscount($subtotal, $mode, $durationValue);

        $coupon = null;
        if ($couponCode !== null && $couponCode !== '') {
            [$couponDiscount, $coupon] = $this->couponDiscount(
                $couponCode, $subtotal, $slot?->date?->format('Y-m-d') ?? now()->toDateString(), $userId
            );
            $discount += $couponDiscount;
        }

        $taxRate = $this->taxRate($tour);
        $amountBeforeTax = max(0, $subtotal - $discount + $transportFee);
        // Tour prices are tax-exclusive (same as Hotel tax-exclusive branch).
        $tax = round($amountBeforeTax * $taxRate);
        $total = $amountBeforeTax + $tax;

        return new PriceBreakdown(
            subtotal: round($subtotal),
            discount: round($discount),
            tax: round($tax),
            total: round(max(0, $total)),
            currency: config('tour.currency', 'VND'),
            couponCode: $couponCode,
            couponId: $coupon?->id,
            addOnAmount: round($transportFee),
            taxInclusive: false,
            taxRate: $taxRate,
            taxName: config('booking.default_tax_name', 'VAT'),
        );
    }

    protected function volumeDiscount(float $subtotal, TourPricingMode $mode, int $duration): float
    {
        if ($mode === TourPricingMode::HOUR && $duration >= config('tour.long_hour_threshold', 8)) {
            return $subtotal * (float) config('tour.long_hour_discount', 0.05);
        }
        if ($mode === TourPricingMode::DAY && $duration >= config('tour.long_day_threshold', 3)) {
            return $subtotal * (float) config('tour.long_day_discount', 0.05);
        }

        return 0.0;
    }

    /**
     * @return array{0: float, 1: \App\Models\Coupon|null}
     */
    protected function couponDiscount(string $code, float $subtotal, string $date, ?int $userId): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->where('is_active', true)->first();
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
        if ($coupon->usage_limit_total !== null && $coupon->redemptionCount() >= $coupon->usage_limit_total) {
            return [0.0, null];
        }
        if ($userId !== null && $coupon->usage_limit_per_user !== null
            && $coupon->redemptionCountForUser($userId) >= $coupon->usage_limit_per_user) {
            return [0.0, null];
        }

        return [$this->coupons->computeDiscount($coupon, $subtotal), $coupon];
    }

    protected function taxRate(TourProduct $tour): float
    {
        $tour->loadMissing('province.country');
        $rate = $tour->province?->country?->tax_rate;

        return $rate !== null ? (float) $rate : (float) config('booking.default_tax_rate', 0);
    }
}
