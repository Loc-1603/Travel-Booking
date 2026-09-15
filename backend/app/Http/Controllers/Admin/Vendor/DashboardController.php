<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\TourBooking;
use App\Models\TourProvider;
use App\Services\CommissionService;
use App\Services\TourCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __construct(
        protected CommissionService $commissionService,
        protected TourCommissionService $tourCommissionService
    ) {}

    public function index(Request $request)
    {
        $userId = auth()->id();
        $hotelIds = Hotel::where('vendor_id', $userId)->pluck('id');
        $providerIds = TourProvider::where('vendor_id', $userId)->pluck('id');

        $segment = $request->query('segment');
        if (! in_array($segment, ['hotel', 'tour'], true)) {
            // Auto: show tour when vendor has no hotels but has tour providers.
            $segment = ($hotelIds->isEmpty() && $providerIds->isNotEmpty()) ? 'tour' : 'hotel';
        }

        if ($segment === 'tour') {
            $revenue = (float) TourBooking::whereIn('provider_id', $providerIds)
                ->whereIn('status', TourCommissionService::REVENUE_STATUSES)
                ->whereNull('deleted_at')
                ->sum('total_price');
            $bookingCount = TourBooking::whereIn('provider_id', $providerIds)->whereNull('deleted_at')->count();
            $rate = $this->tourCommissionService->getCommissionRate();
            $commissionAmount = round($revenue * $rate, 2);
            $net = $revenue - $commissionAmount;

            $revenueChart = $this->tourRevenueChartData($providerIds);
            $bookingsByStatus = $this->tourBookingsByStatusData($providerIds);
            $bookingsTrendChart = $this->tourBookingsTrendChartData($providerIds);
            $topHotelsChart = $this->topProvidersByRevenueData($providerIds);
        } else {
            $revenue = (float) Booking::whereIn('hotel_id', $hotelIds)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->sum('total_price');
            $bookingCount = Booking::whereIn('hotel_id', $hotelIds)->whereNull('deleted_at')->count();
            $rate = $this->commissionService->getCommissionRate();
            $commissionAmount = round($revenue * $rate, 2);
            $net = $revenue - $commissionAmount;

            $revenueChart = $this->revenueChartData($hotelIds);
            $bookingsByStatus = $this->bookingsByStatusData($hotelIds);
            $bookingsTrendChart = $this->bookingsTrendChartData($hotelIds);
            $topHotelsChart = $this->topHotelsByRevenueData($hotelIds);
        }
        $vendorApproved = auth()->user()->isVendorApproved();

        return view('admin.vendor.dashboard', compact(
            'revenue', 'bookingCount', 'commissionAmount', 'net', 'revenueChart', 'bookingsByStatus', 'bookingsTrendChart', 'topHotelsChart', 'vendorApproved', 'segment'
        ));
    }

    protected function revenueChartData($hotelIds): array
    {
        $rows = DB::table('bookings')
            ->whereIn('hotel_id', $hotelIds)
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->where('check_in', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw($this->monthExpression('check_in').' as month, SUM(total_price) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');
            $labels[] = $date->locale('vi')->isoFormat('MMM YYYY');
            $data[] = (float) ($rows[$month] ?? 0);
        }
        return ['labels' => $labels, 'data' => $data];
    }

    protected function bookingsByStatusData($hotelIds): array
    {
        $rows = DB::table('bookings')
            ->whereIn('hotel_id', $hotelIds)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        $labels = [
            __('admin.status.pending'),
            __('admin.status.confirmed'),
            __('admin.status.cancelled'),
            __('admin.status.completed'),
        ];
        $data = [];
        foreach ($statuses as $s) {
            $data[] = (int) ($rows[$s] ?? 0);
        }
        return ['labels' => $labels, 'data' => $data];
    }

    protected function bookingsTrendChartData($hotelIds): array
    {
        $rows = DB::table('bookings')
            ->whereIn('hotel_id', $hotelIds)
            ->whereNull('deleted_at')
            ->where('check_in', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw($this->monthExpression('check_in').' as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');
            $labels[] = $date->locale('vi')->isoFormat('MMM YYYY');
            $data[] = (int) ($rows[$month] ?? 0);
        }
        return ['labels' => $labels, 'data' => $data];
    }

    protected function topHotelsByRevenueData($hotelIds): array
    {
        $rows = DB::table('bookings')
            ->join('hotels', 'bookings.hotel_id', '=', 'hotels.id')
            ->whereIn('bookings.hotel_id', $hotelIds)
            ->where('bookings.status', 'completed')
            ->whereNull('bookings.deleted_at')
            ->selectRaw('hotels.name as hotel_name, SUM(bookings.total_price) as total')
            ->groupBy('hotels.id', 'hotels.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'labels' => $rows->map(fn ($r) => Str::limit($r->hotel_name, 20))->values()->all(),
            'data' => $rows->map(fn ($r) => (float) $r->total)->values()->all(),
        ];
    }

    protected function tourRevenueChartData($providerIds): array
    {
        $rows = DB::table('tour_bookings')
            ->whereIn('provider_id', $providerIds)
            ->whereIn('status', TourCommissionService::REVENUE_STATUSES)
            ->whereNull('deleted_at')
            ->where('start_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw($this->monthExpression('start_at').' as month, SUM(total_price) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        return $this->monthLabels($rows, true);
    }

    /**
     * 4 core tour statuses to mirror the hotel chart.
     */
    protected function tourBookingsByStatusData($providerIds): array
    {
        $rows = DB::table('tour_bookings')
            ->whereIn('provider_id', $providerIds)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statuses = ['pending_payment', 'confirmed', 'cancelled', 'completed'];
        $labels = [
            __('admin.status.pending_payment'),
            __('admin.status.confirmed'),
            __('admin.status.cancelled'),
            __('admin.status.completed'),
        ];
        $data = [];
        foreach ($statuses as $s) {
            $data[] = (int) ($rows[$s] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    protected function tourBookingsTrendChartData($providerIds): array
    {
        $rows = DB::table('tour_bookings')
            ->whereIn('provider_id', $providerIds)
            ->whereNull('deleted_at')
            ->where('start_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw($this->monthExpression('start_at').' as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        return $this->monthLabels($rows, false);
    }

    /**
     * Top 5 providers of this vendor by completed tour revenue.
     */
    protected function topProvidersByRevenueData($providerIds): array
    {
        $rows = DB::table('tour_bookings')
            ->join('tour_providers', 'tour_bookings.provider_id', '=', 'tour_providers.id')
            ->whereIn('tour_bookings.provider_id', $providerIds)
            ->whereIn('tour_bookings.status', TourCommissionService::REVENUE_STATUSES)
            ->whereNull('tour_bookings.deleted_at')
            ->selectRaw('tour_providers.business_name as provider_name, SUM(tour_bookings.total_price) as total')
            ->groupBy('tour_providers.id', 'tour_providers.business_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'labels' => $rows->map(fn ($r) => Str::limit($r->provider_name, 20))->values()->all(),
            'data' => $rows->map(fn ($r) => (float) $r->total)->values()->all(),
        ];
    }

    protected function monthLabels($rows, bool $isMoney): array
    {
        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');
            $labels[] = $date->locale('vi')->isoFormat('MMM YYYY');
            $data[] = $isMoney ? (float) ($rows[$month] ?? 0) : (int) ($rows[$month] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * MySQL uses DATE_FORMAT, SQLite (tests) uses strftime.
     */
    protected function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
