<?php

namespace App\Services;

use App\Models\TourBooking;
use Illuminate\Support\Facades\DB;

class TourCommissionService
{
    /**
     * Booking statuses that count as earned revenue. Paid tours move
     * confirmed → ongoing → completed via TourAutoComplete, so counting
     * only 'confirmed' would drop every tour that already took place.
     */
    public const REVENUE_STATUSES = ['confirmed', 'ongoing', 'completed'];

    protected float $commissionRate;

    public function __construct(?float $commissionRate = null)
    {
        if ($commissionRate !== null) {
            $this->commissionRate = $commissionRate;

            return;
        }

        $this->commissionRate = (float) config('tour.commission_rate', config('booking.commission_rate', 0.10));
    }

    public function getCommissionRate(): float
    {
        return $this->commissionRate;
    }

    /**
     * Commission amount for a tour booking (from total price).
     */
    public function commissionForTourBooking(TourBooking $booking): float
    {
        return round((float) $booking->total_price * $this->commissionRate);
    }

    /**
     * Vendor net earnings: tour total minus commission minus refunds.
     */
    public function vendorNetForTourBooking(TourBooking $booking): float
    {
        $total = (float) $booking->total_price;
        $commission = $this->commissionForTourBooking($booking);
        $refunded = (float) $booking->payments()->sum('refunded_amount');

        return round($total - $commission - $refunded);
    }

    /**
     * Admin reporting: aggregates revenue-eligible tour bookings by vendor
     * (tour_providers.vendor_id) with optional date range on start_at.
     * Mirrors CommissionService::reportByVendor for hotels.
     */
    public function reportByVendor(?string $from = null, ?string $to = null): array
    {
        $query = DB::table('tour_bookings')
            ->join('tour_providers', 'tour_bookings.provider_id', '=', 'tour_providers.id')
            ->whereIn('tour_bookings.status', self::REVENUE_STATUSES)
            ->whereNull('tour_bookings.deleted_at')
            ->select(
                'tour_providers.vendor_id',
                DB::raw('COUNT(tour_bookings.id) as booking_count'),
                DB::raw('SUM(tour_bookings.total_price) as gross')
            )
            ->selectRaw('SUM(tour_bookings.total_price) * ? as commission', [$this->commissionRate])
            ->selectRaw('SUM(tour_bookings.total_price) * (1 - ?) as net', [$this->commissionRate])
            ->groupBy('tour_providers.vendor_id');

        if ($from) {
            $query->whereDate('tour_bookings.start_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('tour_bookings.start_at', '<=', $to);
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    /**
     * Platform-wide tour totals for KPIs (revenue, commission, booking count).
     *
     * @return array{revenue: float, commission: float, booking_count: int}
     */
    public function platformTotals(?string $from = null, ?string $to = null): array
    {
        $query = DB::table('tour_bookings')
            ->whereIn('tour_bookings.status', self::REVENUE_STATUSES)
            ->whereNull('tour_bookings.deleted_at');
        if ($from) {
            $query->whereDate('tour_bookings.start_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('tour_bookings.start_at', '<=', $to);
        }
        $revenue = (float) (clone $query)->sum('tour_bookings.total_price');
        $bookingCount = (int) (clone $query)->count();
        $commission = round($revenue * $this->commissionRate);

        return [
            'revenue' => $revenue,
            'commission' => $commission,
            'booking_count' => $bookingCount,
        ];
    }
}
