<?php

namespace App\Services;

use App\Models\TourBooking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TourPayoutService
{
    /**
     * Confirmed tour bookings in a period not yet included in any payout.
     *
     * @return Collection<int, TourBooking>
     */
    public function unpaidConfirmedForPeriod(string $periodStart, string $periodEnd): Collection
    {
        return TourBooking::query()
            ->join('tour_providers', 'tour_bookings.provider_id', '=', 'tour_providers.id')
            ->where('tour_bookings.status', 'confirmed')
            ->whereNull('tour_bookings.deleted_at')
            ->whereDate('tour_bookings.start_at', '>=', $periodStart)
            ->whereDate('tour_bookings.start_at', '<=', $periodEnd)
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('payout_tour_booking')
                    ->whereColumn('payout_tour_booking.tour_booking_id', 'tour_bookings.id');
            })
            ->select('tour_bookings.*')
            ->with('provider')
            ->get();
    }
}
