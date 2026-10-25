<?php

namespace App\Services;

use App\Enums\TourBookingStatus;
use App\Repositories\Contracts\TourAvailabilitySlotRepositoryInterface;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Repositories\Contracts\TourProductRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Create/cancel 1vs1 tour bookings.
 */
class TourBookingService
{
    public function __construct(
        protected TourAvailabilityService $availability,
        protected TourPricingService $pricing,
        protected TourBookingRepositoryInterface $bookings,
        protected TourProductRepositoryInterface $products,
        protected TourAvailabilitySlotRepositoryInterface $slots,
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
    ): \App\Models\TourBooking {
        return DB::transaction(function () use ($customerId, $tourId, $slotId, $pricingMode, $durationValue, $couponCode, $meetingPoint, $customerNotes, $currency) {
            $tour = $this->products->findPublishedById($tourId);
            if (! $tour) {
                throw new \RuntimeException('Tour is not available for booking.');
            }

            $slot = $this->availability->lockSlot($slotId, $tourId);

            $breakdown = $this->pricing->calculate($tour, $slot, $pricingMode, $durationValue, $couponCode, $customerId, true);

            $mode = $pricingMode === 'day' ? 'day' : 'hour';
            $unitPrice = $slot->price_override !== null
                ? (float) $slot->price_override
                : ($mode === 'day' ? (float) $tour->base_price_daily : (float) $tour->base_price_hourly);

            try {
                $startAt = Carbon::parse($slot->date->format('Y-m-d').' '.(is_string($slot->start_time) ? $slot->start_time : $slot->start_time->format('H:i:s')));
                $endAt = $mode === 'day'
                    ? $startAt->copy()->addDays($durationValue)
                    : $startAt->copy()->addHours($durationValue);

                $booking = $this->bookings->create([
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
                $this->availability->releaseSlot($slot);
                throw $e;
            }
        });
    }

    public function resolveGuideTourSlot(string $providerUuid, ?int $slotId = null, ?string $travelDate = null): array
    {
        if ($slotId === null) {
            if ($travelDate === null) {
                throw new \RuntimeException('Travel date is required.');
            }
            $day = Carbon::parse($travelDate)->toDateString();
            $slot = $this->slots->findGuideSlot($providerUuid, $day);
            if (! $slot || ! $slot->tour) {
                throw new \RuntimeException('Guide is not free on the travel date.');
            }

            return ['tour' => $slot->tour, 'slot' => $slot];
        }

        $slot = $this->slots->findWithTourProvider($slotId);
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

    public function createGuideBooking(
        int $customerId,
        string $providerUuid,
        ?int $slotId,
        ?string $travelDate = null,
        ?string $couponCode = null,
        ?string $meetingPoint = null,
        ?string $customerNotes = null,
        string $currency = 'VND',
    ): \App\Models\TourBooking {
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

    /**
     * Cancel pending bookings past the hold TTL and release their slots.
     * Returns the number of candidate bookings examined (same as the command).
     */
    public function expirePendingBookings(): int
    {
        $ttl = (int) config('tour.hold_ttl_minutes', 15);
        $cutoff = Carbon::now()->subMinutes($ttl);
        $count = 0;

        foreach ($this->bookings->expiredPendingIds($cutoff) as $bookingId) {
            DB::transaction(function () use ($bookingId) {
                $booking = $this->bookings->findByIdForUpdate((int) $bookingId);

                if (! $booking
                    || $booking->status !== TourBookingStatus::PENDING_PAYMENT->value
                    || ! $booking->isExpired()) {
                    return;
                }

                $slot = $this->slots->findByIdForUpdate((int) $booking->slot_id);
                if ($slot) {
                    $this->availability->releaseSlot($slot);
                }

                $this->bookings->update($booking, ['status' => TourBookingStatus::CANCELLED->value]);
            });

            $count++;
        }

        return $count;
    }

    /**
     * Advance confirmed -> ongoing -> completed by time.
     *
     * @return array{ongoing: int, completed: int}
     */
    public function advanceTimedStatuses(): array
    {
        return $this->bookings->advanceTimedStatuses();
    }

    public function cancelBooking(\App\Models\TourBooking $booking): void
    {
        if ($booking->status === TourBookingStatus::CANCELLED->value) {
            return;
        }

        DB::transaction(function () use ($booking) {
            $slot = $this->slots->findByIdForUpdate($booking->slot_id);
            if ($slot) {
                $this->availability->releaseSlot($slot);
            }
            $this->bookings->update($booking, ['status' => TourBookingStatus::CANCELLED->value]);
        });
    }

    public function preview(
        int $tourId,
        int $slotId,
        string $pricingMode,
        int $durationValue,
        ?string $couponCode,
        ?int $userId,
    ): \App\DTOs\PriceBreakdown {
        $tour = $this->products->findByIdOrFail($tourId);
        $slot = $this->slots->findById($slotId);

        return $this->pricing->calculate($tour, $slot, $pricingMode, $durationValue, $couponCode, $userId);
    }
}
