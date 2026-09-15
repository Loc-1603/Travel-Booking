<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payout;
use App\Models\TourBooking;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    public function __construct(
        protected CommissionService $commissionService,
        protected TourPayoutService $tourPayoutService,
        protected TourCommissionService $tourCommissionService
    ) {}

    /**
     * Generate payouts for a period. Creates one payout per vendor with completed
     * hotel + tour bookings in the period that haven't been included in any
     * payout yet (union payout: hotel pivot + tour pivot on the same row).
     * Only completed stays/tours count as earned revenue (confirmed bookings can
     * still be cancelled). Hotel period matches on check_out (stay finished).
     *
     * @return Payout[] Created payouts
     */
    public function generateForPeriod(string $periodStart, string $periodEnd): array
    {
        $bookings = Booking::query()
            ->join('hotels', 'bookings.hotel_id', '=', 'hotels.id')
            ->where('bookings.status', 'completed')
            ->whereNull('bookings.deleted_at')
            ->whereDate('bookings.check_out', '>=', $periodStart)
            ->whereDate('bookings.check_out', '<=', $periodEnd)
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('payout_booking')
                    ->whereColumn('payout_booking.booking_id', 'bookings.id');
            })
            ->select('bookings.*')
            ->with('hotel')
            ->get();

        $tourBookings = $this->tourPayoutService->unpaidConfirmedForPeriod($periodStart, $periodEnd);

        /** @var array<int, array{hotel: \Illuminate\Support\Collection<int, Booking>, tour: \Illuminate\Support\Collection<int, TourBooking>}> $buckets */
        $buckets = [];
        foreach ($bookings as $booking) {
            $buckets[$booking->hotel->vendor_id]['hotel'][] = $booking;
        }
        foreach ($tourBookings as $tourBooking) {
            $buckets[$tourBooking->provider->vendor_id]['tour'][] = $tourBooking;
        }

        $created = [];
        foreach ($buckets as $vendorId => $bucket) {
            $hotelBookings = collect($bucket['hotel'] ?? []);
            $vendorTourBookings = collect($bucket['tour'] ?? []);

            $amount = 0;
            $commission = 0;
            foreach ($hotelBookings as $booking) {
                $amount += (float) $booking->total_price;
                $commission += $this->commissionService->commissionForBooking($booking);
            }
            foreach ($vendorTourBookings as $tourBooking) {
                $amount += (float) $tourBooking->total_price;
                $commission += $this->tourCommissionService->commissionForTourBooking($tourBooking);
            }
            $net = round($amount - $commission);
            if ($net <= 0) {
                continue;
            }

            $payout = Payout::create([
                'vendor_id' => $vendorId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'amount' => round($amount),
                'commission' => round($commission),
                'net' => $net,
                'status' => Payout::STATUS_PENDING,
            ]);

            if ($hotelBookings->isNotEmpty()) {
                $payout->bookings()->attach($hotelBookings->pluck('id'));
            }
            if ($vendorTourBookings->isNotEmpty()) {
                $payout->tourBookings()->attach($vendorTourBookings->pluck('id'));
            }
            $created[] = $payout;
        }

        return $created;
    }
}
