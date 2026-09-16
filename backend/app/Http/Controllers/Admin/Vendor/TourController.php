<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourController extends Controller
{
    /**
     * Provider IDs owned by the authenticated vendor (auto-create one on first use).
     */
    protected function myProviderIds(): array
    {
        return TourProvider::where('vendor_id', auth()->id())->pluck('id')->all();
    }

    protected function myProviderOrFail(int $providerId): TourProvider
    {
        return TourProvider::where('id', $providerId)->where('vendor_id', auth()->id())->firstOrFail();
    }

    public function index(Request $request): View
    {
        $this->authorize('create', TourProduct::class);
        $query = TourProduct::whereIn('provider_id', $this->myProviderIds())
            ->with(['provider', 'province']);
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $tours = $query->latest()->paginate(15)->withQueryString();

        return view('admin.vendor.tours.index', compact('tours'));
    }

    public function create(): View
    {
        $this->authorize('create', TourProduct::class);
        $providers = TourProvider::where('vendor_id', auth()->id())->orderBy('business_name')->get();
        $provinces = TourProvince::orderBy('name')->get();

        return view('admin.vendor.tours.create', compact('providers', 'provinces'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', TourProduct::class);
        $validated = $this->validateTour($request);
        $this->myProviderOrFail((int) $validated['provider_id']);
        TourProduct::create($validated);

        return redirect()->route('admin.vendor.tours.index')->with('success', __('admin.vendor.tours.flash.created'));
    }

    public function edit(TourProduct $tour): RedirectResponse|View
    {
        $this->authorize('update', $tour);
        $providers = TourProvider::where('vendor_id', auth()->id())->orderBy('business_name')->get();
        $provinces = TourProvince::orderBy('name')->get();

        return view('admin.vendor.tours.edit', compact('tour', 'providers', 'provinces'));
    }

    public function update(Request $request, TourProduct $tour): RedirectResponse
    {
        $this->authorize('update', $tour);
        $validated = $this->validateTour($request, true);
        if (isset($validated['provider_id'])) {
            $this->myProviderOrFail((int) $validated['provider_id']);
        }
        $tour->update($validated);

        return redirect()->route('admin.vendor.tours.index')->with('success', __('admin.vendor.tours.flash.updated'));
    }

    public function destroy(TourProduct $tour): RedirectResponse
    {
        $this->authorize('update', $tour);
        $tour->update(['status' => 'suspended']);

        return redirect()->route('admin.vendor.tours.index')->with('success', __('admin.vendor.tours.flash.suspended'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateTour(Request $request, bool $withStatus = false): array
    {
        $rules = [
            'provider_id' => 'required|exists:tour_providers,id',
            'province_id' => 'required|exists:tour_provinces,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            // Luồng 1 ngày: chỉ giá ngày là bắt buộc (giá guide tự đặt cho khách).
            // Giữ 2 trường cũ optional = 0 để tương thích dữ liệu/API cũ.
            'base_fixed' => 'nullable|numeric|min:0',
            'base_price_hourly' => 'nullable|numeric|min:0',
            'base_price_daily' => 'required|numeric|min:0',
            'transport_fee' => 'nullable|numeric|min:0',
            'transport_desc' => 'nullable|string|max:1000',
            'meeting_point' => 'nullable|string|max:500',
            'duration_unit' => 'nullable|string|max:20',
        ];
        if ($withStatus) {
            $rules['status'] = 'required|in:draft,published,suspended';
        }
        $validated = $request->validate($rules);
        $validated['status'] ??= 'draft';
        $validated['base_fixed'] ??= 0;
        $validated['base_price_hourly'] ??= 0;
        if (empty($validated['transport_fee'])) {
            $validated['transport_fee'] = null;
        }
        $validated['max_group_size'] = 1;

        return $validated;
    }
}
