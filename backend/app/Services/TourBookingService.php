<?php

namespace App\Services;

use App\Enums\TourBookingStatus;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Create/cancel 1vs1 tour bookings. Mirrors BookingService transaction +
 * compensate pattern, but capacity is always 1 (slot-level lock).
 * Tour requires login: customerId is never null (no guest flow).
 */
class TourBookingService
{
    public function __construct(
        protected TourAvailabilityService $availability,
        protected TourPricingService $pricing,
    ) {}

    public function createBooking(
        int $customerId,
        int $tourId,
        int $slotId,
        string $pricingMode,
        int $durationValue,
        ?string $couponCode = null,
        ?string $meetingPoint = null,
        ?string $customerNotes = null,
        string $currency = 'VND',
    ): TourBooking {
        return DB::transaction(function () use ($customerId, $tourId, $slotId, $pricingMode, $durationValue, $couponCode, $meetingPoint, $customerNotes, $currency) {
            $tour = TourProduct::where('id', $tourId)->where('status', 'published')->first();
            if (! $tour) {
                throw new \RuntimeException('Tour is not available for booking.');
            }

            // Pessimistic slot lock (available -> held).
            $slot = $this->availability->lockSlot($slotId, $tourId);

            $breakdown = $this->pricing->calculate($tour, $slot, $pricingMode, $durationValue, $couponCode, $customerId);

            $mode = $pricingMode === 'day' ? 'day' : 'hour';
            $unitPrice = $slot->price_override !== null
                ? (float) $slot->price_override
                : ($mode === 'day' ? (float) $tour->base_price_daily : (float) $tour->base_price_hourly);

            try {
                $startAt = Carbon::parse($slot->date->format('Y-m-d').' '.(is_string($slot->start_time) ? $slot->start_time : $slot->start_time->format('H:i:s')));
                $endAt = $mode === 'day'
                    ? $startAt->copy()->addDays($durationValue)
                    : $startAt->copy()->addHours($durationValue);

                $booking = TourBooking::create([
                    'customer_id' => $customerId,
                    'tour_id' => $tour->id,
                    'slot_id' => $slot->id,
                    'provider_id' => $tour->provider_id,
                    'province_id' => $tour->province_id,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'pricing_mode' => $mode,
                    'duration_value' => $durationValue,
                    'base_fixed' => (float) $tour->base_fixed,
                    'unit_price' => $unitPrice,
                    'subtotal' => $breakdown->subtotal,
                    'transport_fee' => $breakdown->addOnAmount,
                    'discount_amount' => $breakdown->discount,
                    'tax_amount' => $breakdown->tax,
                    'total_price' => $breakdown->total,
                    'currency' => $currency,
                    'status' => TourBookingStatus::PENDING_PAYMENT->value,
                    'coupon_id' => $breakdown->couponId,
                    'meeting_point' => $meetingPoint ?? $tour->meeting_point,
                    'customer_notes' => $customerNotes,
                ]);

                $this->availability->confirmSlot($slot, $booking->id);

                return $booking;
            } catch (\Throwable $e) {
                $this->availability->releaseSlot($slot->fresh());
                throw $e;
            }
        });
    }

    /**
     * Luồng đặt 1 ngày theo Guide: resolve tour từ slot (slot phải thuộc
     * tour published của đúng provider đã duyệt), giá luôn = 1 ngày.
     * Khi $slotId = null (UI đặt theo ngày, không chọn giờ), tự lấy slot
     * trống sớm nhất của provider trong đúng $travelDate.
     *
     * @return array{tour: TourProduct, slot: TourAvailabilitySlot}
     *
     * @throws \RuntimeException
     */
    public function resolveGuideTourSlot(string $providerUuid, ?int $slotId, ?string $travelDate = null): array
    {
        if ($slotId === null) {
            if ($travelDate === null) {
                throw new \RuntimeException('Travel date is required.');
            }
            $day = Carbon::parse($travelDate)->toDateString();
            $slot = TourAvailabilitySlot::query()
                ->with('tour.provider')
                ->join('tour_products', 'tour_products.id', '=', 'tour_availability_slots.tour_id')
                ->join('tour_providers', 'tour_providers.id', '=', 'tour_products.provider_id')
                ->where('tour_providers.uuid', $providerUuid)
                ->where('tour_providers.status', 'approved')
                ->where('tour_products.status', 'published')
                ->where('tour_availability_slots.status', 'available')
                ->whereDate('tour_availability_slots.date', '=', $day)
                ->orderBy('tour_availability_slots.start_time')
                ->select('tour_availability_slots.*')
                ->first();
            if (! $slot || ! $slot->tour) {
                throw new \RuntimeException('Guide is not free on the travel date.');
            }

            return ['tour' => $slot->tour, 'slot' => $slot];
        }

        $slot = TourAvailabilitySlot::with('tour.provider')->find($slotId);
        if (! $slot || ! $slot->tour) {
            throw new \RuntimeException('Tour slot not found.');
        }

        $tour = $slot->tour;
        if ($tour->status !== 'published'
            || ! $tour->provider
            || $tour->provider->uuid !== $providerUuid
            || $tour->provider->status !== 'approved') {
            throw new \RuntimeException('Tour slot does not belong to this guide.');
        }

        if ($travelDate !== null && $slot->date && $slot->date->format('Y-m-d') !== $travelDate) {
            throw new \RuntimeException('Tour slot is not on the travel date.');
        }

        return ['tour' => $tour, 'slot' => $slot];
    }

    /**
     * Tạo booking 1 ngày theo Guide (pricing_mode=day, duration=1).
     */
    public function createGuideBooking(
        int $customerId,
        string $providerUuid,
        ?int $slotId,
        ?string $travelDate = null,
        ?string $couponCode = null,
        ?string $meetingPoint = null,
        ?string $customerNotes = null,
        string $currency = 'VND',
    ): TourBooking {
        $resolved = $this->resolveGuideTourSlot($providerUuid, $slotId, $travelDate);

        return $this->createBooking(
            $customerId,
            $resolved['tour']->id,
            $resolved['slot']->id,
            'day',
            1,
            $couponCode,
            $meetingPoint,
            $customerNotes,
            $currency,
        );
    }

    /**
     * Xem trước giá 1 ngày theo Guide (không lock slot).
     */
    public function previewGuide(
        string $providerUuid,
        ?int $slotId,
        ?string $couponCode,
        ?int $userId,
        ?string $travelDate = null,
    ): \App\DTOs\PriceBreakdown {
        $resolved = $this->resolveGuideTourSlot($providerUuid, $slotId, $travelDate);

        return $this->preview($resolved['tour']->id, $resolved['slot']->id, 'day', 1, $couponCode, $userId);
    }

    public function cancelBooking(TourBooking $booking): void
    {
        if ($booking->status === TourBookingStatus::CANCELLED->value) {
            return;
        }

        DB::transaction(function () use ($booking) {
            $slot = TourAvailabilitySlot::where('id', $booking->slot_id)->lockForUpdate()->first();
            if ($slot) {
                $this->availability->releaseSlot($slot);
            }
            $booking->update(['status' => TourBookingStatus::CANCELLED->value]);
        });
    }

    /**
     * Price preview without creating anything (no lock).
     */
    public function preview(
        int $tourId,
        int $slotId,
        string $pricingMode,
        int $durationValue,
        ?string $couponCode,
        ?int $userId,
    ): \App\DTOs\PriceBreakdown {
        $tour = TourProduct::findOrFail($tourId);
        $slot = TourAvailabilitySlot::find($slotId);

        return $this->pricing->calculate($tour, $slot, $pricingMode, $durationValue, $couponCode, $userId);
    }
}
