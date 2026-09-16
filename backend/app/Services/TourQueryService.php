<?php

namespace App\Services;

use App\Models\TourAvailabilitySlot;
use App\Models\TourProduct;
use App\Models\TourProvider;
use Illuminate\Support\Carbon;

/**
 * Read model for Tour search/detail. Mirrors HotelSearchController query style
 * but scoped to published tours + approved providers (no hotel logic touched).
 */
class TourQueryService
{
    public function baseQuery()
    {
        return TourProduct::query()
            ->where('status', 'published')
            ->whereHas('provider', fn ($q) => $q->where('status', 'approved'));
    }

    public function withRatingStats($query)
    {
        return $query->selectRaw(
            'tour_products.*, (SELECT COALESCE(AVG(tr.rating), 0) FROM tour_reviews tr INNER JOIN tour_bookings tb ON tr.tour_booking_id = tb.id WHERE tb.tour_id = tour_products.id AND tr.approved = 1) as average_rating, (SELECT COUNT(*) FROM tour_reviews tr INNER JOIN tour_bookings tb ON tr.tour_booking_id = tb.id WHERE tb.tour_id = tour_products.id AND tr.approved = 1) as review_count'
        );
    }

    /**
     * Tour IDs that have at least one available slot in [from, to].
     *
     * @return array<int>
     */
    public function tourIdsWithAvailability(string $from, string $to): array
    {
        return TourAvailabilitySlot::whereBetween('date', [$from, $to])
            ->where('status', 'available')
            ->distinct()
            ->pluck('tour_id')
            ->all();
    }

    public function applySort($query, ?string $sort): void
    {
        match ($sort) {
            'price_low' => $query->orderBy('base_price_hourly', 'asc'),
            'price_high' => $query->orderBy('base_price_hourly', 'desc'),
            'rating' => $query->orderByRaw('average_rating DESC'),
            'name' => $query->orderBy('title', 'asc'),
            default => null,
        };
    }

    /**
     * Base query for approved 1vs1 vendors (guides).
     */
    public function providerBaseQuery()
    {
        return TourProvider::query()->where('status', 'approved');
    }

    /**
     * Attach vendor rating stats + ranking score.
     *
     * score = AVG(rating 1..5) * COUNT(approved, visible reviews)
     * via tour_bookings.provider_id — no schema change needed.
     */
    public function withProviderRatingStats($query, ?int $provinceId = null)
    {
        $provinceFilter = $provinceId !== null
            ? ' AND tb.province_id = '.(int) $provinceId
            : '';

        $avgSql = '(SELECT COALESCE(AVG(tr.rating), 0) FROM tour_reviews tr'
            .' INNER JOIN tour_bookings tb ON tr.tour_booking_id = tb.id'
            ." WHERE tb.provider_id = tour_providers.id AND tr.approved = 1 AND (tr.hidden = 0 OR tr.hidden IS NULL){$provinceFilter})";

        $countSql = '(SELECT COUNT(*) FROM tour_reviews tr'
            .' INNER JOIN tour_bookings tb ON tr.tour_booking_id = tb.id'
            ." WHERE tb.provider_id = tour_providers.id AND tr.approved = 1 AND (tr.hidden = 0 OR tr.hidden IS NULL){$provinceFilter})";

        $toursWhere = $provinceId !== null
            ? "provider_id = tour_providers.id AND status = 'published' AND province_id = ".(int) $provinceId
            : "provider_id = tour_providers.id AND status = 'published'";

        return $query->select('tour_providers.*')
            ->selectRaw("{$avgSql} as average_rating")
            ->selectRaw("{$countSql} as review_count")
            ->selectRaw("({$avgSql}) * ({$countSql}) as score")
            ->selectRaw("(SELECT COUNT(*) FROM tour_products WHERE {$toursWhere}) as tours_count")
            // Giá 1 ngày do guide tự thiết lập: dùng giá ngày để sort/hiển thị.
            ->selectRaw("(SELECT MIN(base_price_daily) FROM tour_products WHERE {$toursWhere}) as price_from");
    }

    /**
     * Vendor IDs serving a province (>=1 published tour there).
     *
     * @return array<int>
     */
    public function providerIdsInProvince(int $provinceId): array
    {
        return TourProduct::where('province_id', $provinceId)
            ->where('status', 'published')
            ->whereHas('provider', fn ($q) => $q->where('status', 'approved'))
            ->distinct()
            ->pluck('provider_id')
            ->all();
    }

    /**
     * Vendor IDs free on a single travel_date (luồng 1 ngày khám phá tỉnh).
     * Một guide đạt nếu có >=1 slot available đúng ngày đó
     * trên các tour published của họ trong tỉnh.
     *
     * @return array<int>
     */
    public function providerIdsFreeOnDate(int $provinceId, string $date): array
    {
        $day = Carbon::parse($date)->toDateString();

        $rows = TourAvailabilitySlot::query()
            ->join('tour_products', 'tour_products.id', '=', 'tour_availability_slots.tour_id')
            ->join('tour_providers', 'tour_providers.id', '=', 'tour_products.provider_id')
            ->where('tour_availability_slots.status', 'available')
            ->whereDate('tour_availability_slots.date', '=', $day)
            ->where('tour_products.status', 'published')
            ->where('tour_products.province_id', $provinceId)
            ->where('tour_providers.status', 'approved')
            ->distinct()
            ->pluck('tour_products.provider_id')
            ->all();

        return array_map('intval', $rows);
    }

    /**
     * Vendor IDs free for EVERY day in [from, to] (AND logic).
     * Giữ lại để tương thích ngược với filter khoảng ngày cũ.
     * A vendor passes only if each date in the range has >=1 available
     * slot across their published tours in the province.
     *
     * @return array<int>
     */
    public function providerIdsFreeForAllDays(int $provinceId, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        // Guard against oversized ranges (URL-driven).
        // (int) cast: Carbon 3 diffInDays() returns float, and a float
        // HAVING binding is compared as TEXT by SQLite (INTEGER < TEXT).
        $totalDays = (int) ($start->diffInDays($end) + 1);
        if ($totalDays > 31) {
            $end = $start->copy()->addDays(30);
            $totalDays = 31;
        }

        $rows = TourAvailabilitySlot::query()
            ->join('tour_products', 'tour_products.id', '=', 'tour_availability_slots.tour_id')
            ->join('tour_providers', 'tour_providers.id', '=', 'tour_products.provider_id')
            ->where('tour_availability_slots.status', 'available')
            // whereDate (not whereBetween): SQLite stores DATE columns as
            // 'Y-m-d H:i:s' strings, so a BETWEEN 'Y-m-d' upper bound misses.
            ->whereDate('tour_availability_slots.date', '>=', $start->toDateString())
            ->whereDate('tour_availability_slots.date', '<=', $end->toDateString())
            ->where('tour_products.status', 'published')
            ->where('tour_products.province_id', $provinceId)
            ->where('tour_providers.status', 'approved')
            ->groupBy('tour_products.provider_id')
            ->havingRaw('COUNT(DISTINCT tour_availability_slots.date) >= ?', [$totalDays])
            ->pluck('tour_products.provider_id')
            ->all();

        return array_map('intval', $rows);
    }

    /**
     * Slot trống của 1 guide trong đúng 1 ngày (kèm tour + giá ngày).
     * Chỉ tour published của provider đã duyệt, đúng province (nếu lọc).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TourAvailabilitySlot>
     */
    public function providerSlotsOnDate(string $providerUuid, string $date, ?int $provinceId = null)
    {
        $day = Carbon::parse($date)->toDateString();

        return TourAvailabilitySlot::query()
            ->with('tour:id,uuid,title,province_id,base_price_daily,transport_fee,meeting_point')
            ->join('tour_products', 'tour_products.id', '=', 'tour_availability_slots.tour_id')
            ->join('tour_providers', 'tour_providers.id', '=', 'tour_products.provider_id')
            ->where('tour_providers.uuid', $providerUuid)
            ->where('tour_providers.status', 'approved')
            ->where('tour_products.status', 'published')
            ->when($provinceId !== null, fn ($q) => $q->where('tour_products.province_id', $provinceId))
            ->where('tour_availability_slots.status', 'available')
            ->whereDate('tour_availability_slots.date', '=', $day)
            ->orderBy('tour_availability_slots.start_time')
            ->select('tour_availability_slots.*')
            ->get();
    }

    public function applyProviderSort($query, ?string $sort): void
    {
        match ($sort) {
            'rating' => $query->orderByDesc('average_rating'),
            'price_low' => $query->orderBy('price_from'),
            'price_high' => $query->orderByDesc('price_from'),
            'name' => $query->orderBy('business_name', 'asc'),
            default => $query->orderByDesc('score'),
        };
    }

    /**
     * Single approved vendor with rating stats + tours/slots for GuideDetail.
     */
    public function providerDetail(string $uuid, ?int $provinceId = null): ?TourProvider
    {
        $query = $this->withProviderRatingStats($this->providerBaseQuery(), $provinceId)
            ->with(['vendor', 'tours' => function ($q) use ($provinceId): void {
                $q->where('status', 'published')
                    ->when($provinceId !== null, fn ($qq) => $qq->where('province_id', $provinceId))
                    ->with(['province:id,name,slug,image', 'images'])
                    ->orderBy('title');
            }]);

        return $query->where('tour_providers.uuid', $uuid)->first();
    }

    public function detail(int|string $idOrUuid): ?TourProduct
    {
        $query = $this->withRatingStats($this->baseQuery())
            ->with(['provider.vendor', 'province.attractions', 'images', 'slots' => function ($q) {
                $q->where('date', '>=', Carbon::today()->toDateString())
                    ->where('status', 'available')
                    ->orderBy('date')
                    ->orderBy('start_time')
                    ->limit(60);
            }]);

        if (is_numeric($idOrUuid)) {
            return $query->where('tour_products.id', $idOrUuid)->first();
        }

        return $query->where('tour_products.uuid', $idOrUuid)->first();
    }
}
