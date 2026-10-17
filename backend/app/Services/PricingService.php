<?php

namespace App\Services;

use App\DTOs\PriceBreakdown;
use App\Repositories\Contracts\HotelRepositoryInterface;
use App\Repositories\Contracts\RoomAvailabilityRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Carbon\CarbonPeriod;

class PricingService
{
    public function __construct(
        protected CouponService $couponService,
        protected RoomRepositoryInterface $rooms,
        protected RoomAvailabilityRepositoryInterface $availability,
        protected HotelRepositoryInterface $hotels,
    ) {}

    /**
     * Calculate price breakdown for given rooms and date range.
     * Base price from room (and room_availability price_override when present).
     * Applies length-of-stay discount, optional coupon, and optional late checkout add-on.
     *
     * @param  array<int, int>  $roomQuantities  room_id => quantity
     */
    public function calculate(
        array $roomQuantities,
        string $checkIn,
        string $checkOut,
        int $hotelId,
        ?string $couponCode = null,
        ?int $userId = null,
        bool $lateCheckout = false,
    ): PriceBreakdown {
        $subtotal = 0.0;
        $period = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();

        foreach ($roomQuantities as $roomId => $quantity) {
            $room = $this->rooms->findOrFail($roomId);
            $rows = $this->availability->mapForRangeByDate($roomId, $checkIn, $checkOut);
            foreach ($period as $date) {
                $key = $date->format('Y-m-d');
                $av = $rows[$key] ?? null;
                $pricePerNight = $av && $av->price_override !== null
                    ? (float) $av->price_override
                    : (float) $room->base_price;
                $subtotal += $pricePerNight * $quantity;
            }
        }

        $nights = $period->count();
        [$discount, $coupon] = $this->applyDiscounts($subtotal, $checkIn, $checkOut, $nights, $couponCode, $hotelId, $roomQuantities, $userId);

        $hotel = $this->hotels->findById($hotelId, ['countryRelation']);
        $addOnAmount = 0.0;
        if ($lateCheckout && $hotel && $hotel->late_checkout_price !== null) {
            $addOnAmount = (float) $hotel->late_checkout_price;
        }

        $taxInclusive = $hotel && $hotel->tax_inclusive;
        $taxRate = $this->getTaxRateForHotel($hotelId);
        $taxName = $hotel?->tax_name ?? $hotel?->countryRelation?->tax_name ?? config('booking.default_tax_name', 'Tax');

        $amountBeforeTax = max(0, $subtotal - $discount + $addOnAmount);

        if ($taxInclusive) {
            // Prices include tax: extract tax from the amount
            $tax = $taxRate > 0 ? round($amountBeforeTax * $taxRate / (1 + $taxRate)) : 0.0;
            $total = $amountBeforeTax;
        } else {
            // Tax-exclusive: add tax on top
            $tax = round($amountBeforeTax * $taxRate);
            $total = $amountBeforeTax + $tax;
        }

        return new PriceBreakdown(
            subtotal: round($subtotal),
            discount: round($discount),
            tax: round($tax),
            total: round(max(0, $total)),
            currency: 'VND',
            couponCode: $couponCode,
            couponId: $coupon?->id,
            addOnAmount: round($addOnAmount),
            taxInclusive: $taxInclusive,
            taxRate: $taxRate,
            taxName: $taxName,
        );
    }

    /**
     * @param  array<int, int>  $roomQuantities
     * @return array{0: float, 1: \App\Models\Coupon|null}
     */
    protected function applyDiscounts(float $subtotal, string $checkIn, string $checkOut, int $nights, ?string $couponCode, int $hotelId, array $roomQuantities, ?int $userId): array
    {
        $discount = 0.0;
        if ($nights >= 7) {
            $discount += $subtotal * 0.05;
        }

        $coupon = null;
        if ($couponCode !== null && $couponCode !== '') {
            [$couponDiscount, $coupon] = $this->couponService->validateAndGetDiscount(
                $couponCode,
                $hotelId,
                $roomQuantities,
                $subtotal,
                $checkIn,
                $checkOut,
                $nights,
                $userId,
            );
            $discount += $couponDiscount;
        }

        return [$discount, $coupon];
    }

    protected function getTaxRateForHotel(int $hotelId): float
    {
        $hotel = $this->hotels->findById($hotelId, ['countryRelation']);
        if ($hotel && $hotel->tax_rate !== null) {
            return (float) $hotel->tax_rate;
        }
        if ($hotel?->countryRelation && $hotel->countryRelation->tax_rate !== null) {
            return (float) $hotel->countryRelation->tax_rate;
        }

        return (float) config('booking.default_tax_rate', 0);
    }
}
