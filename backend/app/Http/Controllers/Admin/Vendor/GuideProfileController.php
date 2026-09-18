<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TourProvider;
use Illuminate\Http\RedirectResponse;
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

    public function create(): View
    {
        return view('admin.vendor.guide-profile.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedProviderData($request);
        $validated['business_name'] = trim((string) $request->input('business_name')) ?: $this->defaultBusinessName();

        TourProvider::create(array_merge($validated, [
            'vendor_id' => auth()->id(),
            'status' => 'pending',
        ]));

        return redirect()->route('admin.vendor.guide-profile.index')->with('success', __('admin.vendor.guide_profiles.flash.created'));
    }

    public function edit(TourProvider $provider): View
    {
        $this->authorizeProvider($provider);

        return view('admin.vendor.guide-profile.edit', compact('provider'));
    }

    public function update(Request $request, TourProvider $provider): RedirectResponse
    {
        $this->authorizeProvider($provider);

        $validated = $this->validatedProviderData($request);
        $validated['business_name'] = trim((string) $request->input('business_name')) ?: ($provider->business_name ?: $this->defaultBusinessName());

        $provider->update($validated);

        return redirect()->route('admin.vendor.guide-profile.index')->with('success', __('admin.vendor.guide_profiles.flash.updated'));
    }

    public function destroy(TourProvider $provider): RedirectResponse
    {
        $this->authorizeProvider($provider);

        $provider->delete();

        return redirect()->route('admin.vendor.guide-profile.index')->with('success', __('admin.vendor.guide_profiles.flash.deleted'));
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

    /**
     * Validate + chuẩn hoá dữ liệu hồ sơ (dùng chung cho create/update).
     *
     * @return array<string, mixed>
     */
    private function validatedProviderData(Request $request): array
    {
        $validated = $request->validate([
            'bio' => 'nullable|string|max:2000',
            // Mô tả rich (TipTap): JSON doc + HTML (sẽ sanitize lại phía server).
            'bio_json' => 'nullable|json|max:60000',
            'bio_html' => 'nullable|string|max:60000',
            'languages' => 'nullable|array',
            'languages.*' => 'in:English,Russian,Chinese,Korean,Japanese',
        ]);

        // Chuẩn hoá + sanitize mô tả rich. Giữ bio text cũ làm fallback.
        if (! empty($validated['bio_json'])) {
            $decoded = json_decode($validated['bio_json'], true);
            if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'doc') {
                unset($validated['bio_json']);
            } else {
                // Truyền thẳng mảng để model cast 'array' encode đúng 1 lần
                // (tránh double-encode làm hỏng dữ liệu trên mỗi lần lưu).
                $validated['bio_json'] = $decoded;
            }
        }
        $validated['bio_html'] = \App\Support\RichTextSanitizer::sanitize($validated['bio_html'] ?? null);
        if ($validated['bio_html'] === '') {
            $validated['bio_html'] = null;
        }
        if (empty($validated['bio_json'])) {
            $validated['bio_json'] = null;
        }

        $validated['languages'] = array_values(array_unique($validated['languages'] ?? []));

        return $validated;
    }

    /**
     * business_name mặc định khi vendor không nhập: lấy tên doanh nghiệp
     * trên hồ sơ vendor, nếu không có thì lấy tên người dùng.
     */
    private function defaultBusinessName(): string
    {
        return auth()->user()->vendorProfile?->business_name ?: auth()->user()->name;
    }

    private function authorizeProvider(TourProvider $provider): void
    {
        if ((int) $provider->vendor_id !== (int) auth()->id()) {
            abort(403);
        }
    }
}
