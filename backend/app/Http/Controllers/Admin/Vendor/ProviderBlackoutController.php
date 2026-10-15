<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProviderBlackout;
use App\Models\TourProvince;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProviderBlackoutController extends Controller
{
    public function index(TourProvider $provider): View
    {
        $this->authorizeProvider($provider);
        $provider->load('province');
        $provinces = TourProvince::orderBy('name')->get();
        $blackouts = $provider->blackouts()->orderBy('start_date')->paginate(20);

        return view('admin.vendor.guide-profile.settings', compact('provider', 'provinces', 'blackouts'));
    }

    public function updateProvider(Request $request, TourProvider $provider): RedirectResponse
    {
        $this->authorizeProvider($provider);
        $validated = $request->validate([
            'province_id' => 'nullable|exists:tour_provinces,id',
            'price_daily' => 'nullable|numeric|min:0',
        ]);
        $provider->update($validated);

        // Giá hiển thị người dùng (price_from = MIN(base_price_daily)) và giá
        // đặt tour lấy từ tour_products, nên đồng bộ mọi tour của guide khi
        // giá 1 ngày thay đổi — nếu không, trang người dùng vẫn giữ giá cũ
        // của lần tạo tour đầu tiên.
        if (array_key_exists('price_daily', $validated) && $validated['price_daily'] !== null) {
            TourProduct::where('provider_id', $provider->id)
                ->where('base_price_daily', '!=', $validated['price_daily'])
                ->update(['base_price_daily' => $validated['price_daily']]);
        }

        if (! empty($provider->province_id)) {
            $hasPublishedTour = TourProduct::where('provider_id', $provider->id)
                ->where('province_id', $provider->province_id)
                ->where('status', 'published')
                ->exists();

            if (! $hasPublishedTour) {
                TourProduct::create([
                    'uuid' => Str::uuid(),
                    'provider_id' => $provider->id,
                    'province_id' => $provider->province_id,
                    'attraction_id' => null,
                    'title' => $provider->business_name.' - Tour 1 ngày',
                    'description' => 'Tour mặc định được tạo tự động khi chọn tỉnh.',
                    'duration_hours' => 8,
                    'group_size_min' => 1,
                    'group_size_max' => 12,
                    'base_price_daily' => (float) ($provider->price_daily ?? 0),
                    'transport_fee' => 0,
                    'meeting_point' => optional($provider->province)->name,
                    'status' => 'published',
                ]);
            }
        }

        return back()->with('success', 'Đã cập nhật cài đặt hoạt động');
    }

    public function store(Request $request, TourProvider $provider): RedirectResponse
    {
        $this->authorizeProvider($provider);
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);
        $provider->blackouts()->create($validated);

        return back()->with('success', 'Đã thêm ngày không làm việc');
    }

    public function destroy(TourProvider $provider, TourProviderBlackout $blackout): RedirectResponse
    {
        $this->authorizeProvider($provider);
        if ($blackout->provider_id !== $provider->id) {
            abort(404);
        }
        $blackout->delete();

        return back()->with('success', 'Đã xóa');
    }

    private function authorizeProvider(TourProvider $provider): void
    {
        if ((int) $provider->vendor_id !== (int) auth()->id()) {
            abort(403);
        }
    }
}
