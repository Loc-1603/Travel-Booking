<?php

namespace App\Services;

use App\DTOs\PriceBreakdown;
use App\Enums\TourPricingMode;
use App\Repositories\Contracts\TourProductRepositoryInterface;

/**
 * Tour pricing: total = base_fixed + unit_price * duration + transport_fee
 */
class TourPricingService
{
    public function __construct(
        protected CouponService $coupons,
        protected TourProductRepositoryInterface $products,
    ) {}

    public function calculate(
        \App\Models\TourProduct $tour,
        ?\App\Models\TourAvailabilitySlot $slot,
        string $pricingMode,
        int $durationValue,
        ?string $couponCode = null,
        ?int $userId = null,
        bool $lockCoupon = false,
    ): PriceBreakdown {
        $mode = $pricingMode === TourPricingMode::DAY->value ? TourPricingMode::DAY : TourPricingMode::HOUR;

        $baseFixed = (float) $tour->base_fixed;
        $unitPrice = $mode === TourPricingMode::DAY
            ? (float) $tour->base_price_daily
            : (float) $tour->base_price_hourly;

        if ($slot?->price_override !== null) {
            $unitPrice = (float) $slot->price_override;
        }

        $transportFee = (float) ($tour->transport_fee ?? 0);
        $subtotal = $baseFixed + $unitPrice * $durationValue;

        $discount = $this->volumeDiscount($subtotal, $mode, $durationValue);

        $coupon = null;
        if ($couponCode !== null && $couponCode !== '') {
            [$couponDiscount, $coupon] = $this->couponDiscount(
                $couponCode, $subtotal, $slot?->date?->format('Y-m-d') ?? now()->toDateString(), $userId, $lockCoupon
            );
            $discount += $couponDiscount;
        }

        $taxRate = $this->taxRate($tour);
        $amountBeforeTax = max(0, $subtotal - $discount + $transportFee);
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
    protected function couponDiscount(string $code, float $subtotal, string $date, ?int $userId, bool $lockCoupon = false): array
    {
        return $this->coupons->validateAndGetTourDiscount($code, $subtotal, $date, $userId, $lockCoupon);
    }

    protected function taxRate(\App\Models\TourProduct $tour): float
    {
        $tour = $this->products->loadProvinceCountry($tour);
        $rate = $tour->province?->country?->tax_rate;

        return $rate !== null ? (float) $rate : (float) config('booking.default_tax_rate', 0);
    }
}
