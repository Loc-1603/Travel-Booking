<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TourProviderController extends Controller
{
    public function index(Request $request): View
    {
        $query = TourProvider::with(['vendor', 'tours'])->latest();
        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $request->status);
        }
        $providers = $query->paginate(15)->withQueryString();

        return view('admin.tour-providers.index', compact('providers'));
    }

    public function show(TourProvider $tourProvider): View
    {
        $tourProvider->load(['vendor', 'tours', 'bookings']);

        return view('admin.tour-providers.show', compact('tourProvider'));
    }

    public function edit(TourProvider $tourProvider): View
    {
        $tourProvider->load('vendor');

        return view('admin.tour-providers.edit', compact('tourProvider'));
    }

    public function update(Request $request, TourProvider $tourProvider): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:2000',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',
            'languages' => 'nullable|string|max:500',
        ]);

        $removeAvatar = (bool) ($validated['remove_avatar'] ?? false);
        unset($validated['remove_avatar']);

        $validated['languages'] = collect(explode(',', $validated['languages'] ?? ''))
            ->map(fn ($l) => strtolower(trim($l)))
            ->filter()
            ->values()
            ->all();

        $rawAvatar = $tourProvider->getRawOriginal('avatar');
        $isLocalFile = $rawAvatar && ! str_starts_with($rawAvatar, 'http');

        if ($request->hasFile('avatar')) {
            if ($isLocalFile && Storage::disk('public')->exists($rawAvatar)) {
                Storage::disk('public')->delete($rawAvatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars/tour-providers', 'public');
        } elseif ($removeAvatar) {
            if ($isLocalFile && Storage::disk('public')->exists($rawAvatar)) {
                Storage::disk('public')->delete($rawAvatar);
            }
            $validated['avatar'] = null;
        } else {
            unset($validated['avatar']);
        }

        $tourProvider->update($validated);

        return redirect()->route('admin.tour-providers.show', $tourProvider)->with('success', __('admin.vendor.tour_providers.flash.updated'));
    }

    public function approve(TourProvider $tourProvider): RedirectResponse
    {
        $tourProvider->update(['status' => 'approved']);

        return redirect()->back()->with('success', __('admin.vendor.tour_providers.flash.approved'));
    }

    public function reject(Request $request, TourProvider $tourProvider): RedirectResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $tourProvider->update(['status' => 'rejected']);

        return redirect()->back()->with('success', __('admin.vendor.tour_providers.flash.rejected'));
    }
}
