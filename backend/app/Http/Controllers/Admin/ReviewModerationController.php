<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hậu kiểm review dùng chung (hotel + tour/guide): review hiển thị ngay,
 * admin chỉ ẩn/bỏ ẩn.
 */
class ReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Review::with([
            'booking.hotel',
            'booking.customer',
            'tourBooking.tour',
            'tourBooking.customer',
            'moderatedBy',
        ]);
        if ($request->filled('filter')) {
            match ($request->filter) {
                'visible' => $query->where('hidden', false),
                'hidden' => $query->where('hidden', true),
                default => null,
            };
        }
        if ($request->filled('type')) {
            match ($request->type) {
                'hotel' => $query->whereNull('tour_booking_id'),
                'tour' => $query->whereNotNull('tour_booking_id'),
                default => null,
            };
        }
        $reviews = $query->latest()->paginate(15)->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    }

    public function show(Review $review): View
    {
        $review->load([
            'booking.hotel',
            'booking.customer',
            'tourBooking.tour',
            'tourBooking.customer',
            'moderatedBy',
            'images',
        ]);

        return view('admin.reviews.show', compact('review'));
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:hide,unhide',
        ]);
        $data = ['moderated_at' => now(), 'moderated_by' => auth()->id()];
        $data['hidden'] = $validated['action'] === 'hide';
        $review->update($data);

        return redirect()->route('admin.reviews.show', array_merge([$review], request()->query()))->with('success', __('admin.vendor.reviews.flash.updated'));
    }
}
