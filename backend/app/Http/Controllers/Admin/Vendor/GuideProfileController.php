<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TourProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideProfileController extends Controller
{
    /**
     * Guide profiles owned by the logged-in vendor.
     */
    public function index(): View
    {
        $providers = TourProvider::where('vendor_id', auth()->id())
            ->withCount('tours')
            ->orderBy('business_name')
            ->get();

        return view('admin.vendor.guide-profile.index', compact('providers'));
    }

    public function edit(TourProvider $provider): View
    {
        $this->authorizeProvider($provider);

        return view('admin.vendor.guide-profile.edit', compact('provider'));
    }

    public function update(Request $request, TourProvider $provider): RedirectResponse
    {
        $this->authorizeProvider($provider);

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

        $rawAvatar = $provider->getRawOriginal('avatar');
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

        $provider->update($validated);

        return redirect()->route('admin.vendor.guide-profile.index')->with('success', 'Guide profile updated.');
    }

    private function authorizeProvider(TourProvider $provider): void
    {
        if ((int) $provider->vendor_id !== (int) auth()->id()) {
            abort(403);
        }
    }
}
