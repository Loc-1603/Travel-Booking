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
            // Mô tả rich (TipTap): JSON doc + HTML (sẽ sanitize lại phía server).
            'bio_json' => 'nullable|json|max:60000',
            'bio_html' => 'nullable|string|max:60000',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',
            'languages' => 'nullable|string|max:500',
        ]);

        // Chuẩn hoá + sanitize mô tả rich. Giữ bio text cũ làm fallback.
        if (! empty($validated['bio_json'])) {
            $decoded = json_decode($validated['bio_json'], true);
            if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'doc') {
                unset($validated['bio_json']);
            } else {
                $validated['bio_json'] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        $validated['bio_html'] = \App\Support\RichTextSanitizer::sanitize($validated['bio_html'] ?? null);
        if ($validated['bio_html'] === '') {
            $validated['bio_html'] = null;
        }
        if (empty($validated['bio_json'])) {
            $validated['bio_json'] = null;
        }

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

        return redirect()->route('admin.vendor.guide-profile.index')->with('success', __('admin.vendor.guide_profiles.flash.updated'));
    }

    /**
     * Upload ảnh chèn vào mô tả rich (TipTap). Trả JSON {url}.
     */
    public function storeContentImage(Request $request, TourProvider $provider): \Illuminate\Http\JsonResponse
    {
        $this->authorizeProvider($provider);

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        $path = $request->file('image')->store('guide-content', 'public');

        return response()->json(['url' => asset('storage/'.$path)]);
    }

    private function authorizeProvider(TourProvider $provider): void
    {
        if ((int) $provider->vendor_id !== (int) auth()->id()) {
            abort(403);
        }
    }
}
