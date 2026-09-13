<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourAttraction;
use App\Models\TourProvince;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TourAttractionController extends Controller
{
    public function index(Request $request): View
    {
        $query = TourAttraction::with('province')->latest();
        if ($request->filled('province_id')) {
            $query->where('province_id', $request->province_id);
        }
        $attractions = $query->paginate(15)->withQueryString();
        $provinces = TourProvince::orderBy('name')->get();

        return view('admin.tour-attractions.index', compact('attractions', 'provinces'));
    }

    public function create(Request $request): View
    {
        $provinces = TourProvince::orderBy('name')->get();
        $selectedProvinceId = $request->input('province_id');

        return view('admin.tour-attractions.create', compact('provinces', 'selectedProvinceId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'province_id' => 'required|exists:tour_provinces,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_famous' => 'nullable|boolean',
        ]);
        $validated['is_famous'] = (bool) ($validated['is_famous'] ?? false);
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('locations/tour-attractions', 'public');
        }
        TourAttraction::create($validated);

        return redirect()->route('admin.tour-attractions.index')->with('success', 'Attraction created.');
    }

    public function edit(TourAttraction $tourAttraction): View
    {
        $provinces = TourProvince::orderBy('name')->get();

        return view('admin.tour-attractions.edit', compact('tourAttraction', 'provinces'));
    }

    public function update(Request $request, TourAttraction $tourAttraction): RedirectResponse
    {
        $validated = $request->validate([
            'province_id' => 'required|exists:tour_provinces,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_famous' => 'nullable|boolean',
        ]);
        $validated['is_famous'] = (bool) ($validated['is_famous'] ?? false);
        if ($request->hasFile('image')) {
            $rawImage = $tourAttraction->getRawOriginal('image');
            if ($rawImage && ! str_starts_with($rawImage, 'http') && Storage::disk('public')->exists($rawImage)) {
                Storage::disk('public')->delete($rawImage);
            }
            $validated['image'] = $request->file('image')->store('locations/tour-attractions', 'public');
        }
        $tourAttraction->update($validated);

        return redirect()->route('admin.tour-attractions.index')->with('success', 'Attraction updated.');
    }

    public function destroy(TourAttraction $tourAttraction): RedirectResponse
    {
        $rawImage = $tourAttraction->getRawOriginal('image');
        if ($rawImage && ! str_starts_with($rawImage, 'http') && Storage::disk('public')->exists($rawImage)) {
            Storage::disk('public')->delete($rawImage);
        }
        $tourAttraction->delete();

        return redirect()->route('admin.tour-attractions.index')->with('success', 'Attraction deleted.');
    }
}
