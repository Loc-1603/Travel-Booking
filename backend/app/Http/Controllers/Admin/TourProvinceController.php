<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\TourProvince;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TourProvinceController extends Controller
{
    public function index(): View
    {
        $provinces = TourProvince::with(['country', 'city'])->withCount(['attractions', 'tours'])->latest()->paginate(15);

        return view('admin.tour-provinces.index', compact('provinces'));
    }

    public function create(): View
    {
        $countries = Country::orderBy('name')->get();
        $cities = City::with('country')->orderBy('name')->get();

        return view('admin.tour-provinces.create', compact('countries', 'cities'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tour_provinces,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']).'-'.Str::lower(Str::random(6));
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('locations/tour-provinces', 'public');
        }
        TourProvince::create($validated);

        return redirect()->route('admin.tour-provinces.index')->with('success', __('admin.vendor.tour_provinces.flash.created'));
    }

    public function edit(TourProvince $tourProvince): View
    {
        $countries = Country::orderBy('name')->get();
        $cities = City::with('country')->orderBy('name')->get();

        return view('admin.tour-provinces.edit', compact('tourProvince', 'countries', 'cities'));
    }

    public function update(Request $request, TourProvince $tourProvince): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tour_provinces,slug,'.$tourProvince->id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        if (! empty($validated['slug'])) {
            $tourProvince->slug = $validated['slug'];
        }
        unset($validated['slug']);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        if ($request->hasFile('image')) {
            $rawImage = $tourProvince->getRawOriginal('image');
            if ($rawImage && ! str_starts_with($rawImage, 'http') && Storage::disk('public')->exists($rawImage)) {
                Storage::disk('public')->delete($rawImage);
            }
            $validated['image'] = $request->file('image')->store('locations/tour-provinces', 'public');
        }
        $tourProvince->update($validated);

        return redirect()->route('admin.tour-provinces.index')->with('success', __('admin.vendor.tour_provinces.flash.updated'));
    }

    public function destroy(TourProvince $tourProvince): RedirectResponse
    {
        $rawImage = $tourProvince->getRawOriginal('image');
        if ($rawImage && ! str_starts_with($rawImage, 'http') && Storage::disk('public')->exists($rawImage)) {
            Storage::disk('public')->delete($rawImage);
        }
        $tourProvince->delete();

        return redirect()->route('admin.tour-provinces.index')->with('success', __('admin.vendor.tour_provinces.flash.deleted'));
    }
}
