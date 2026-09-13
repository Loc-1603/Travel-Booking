<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $query = TourReview::with(['booking.tour', 'booking.customer', 'moderatedBy']);
        if ($request->filled('filter')) {
            match ($request->filter) {
                'pending' => $query->where('approved', false)->where('hidden', false),
                'approved' => $query->where('approved', true)->where('hidden', false),
                'rejected' => $query->where('approved', false),
                'hidden' => $query->where('hidden', true),
                default => null,
            };
        }
        $reviews = $query->latest()->paginate(15)->withQueryString();

        return view('admin.tour-reviews.index', compact('reviews'));
    }

    public function show(TourReview $tourReview): View
    {
        $tourReview->load(['booking.tour', 'booking.customer', 'moderatedBy']);

        return view('admin.tour-reviews.show', compact('tourReview'));
    }

    public function update(Request $request, TourReview $tourReview): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,hide,unhide',
        ]);
        $data = ['moderated_at' => now(), 'moderated_by' => auth()->id()];
        switch ($validated['action']) {
            case 'approve':
                $data['approved'] = true;
                $data['hidden'] = false;
                break;
            case 'reject':
                $data['approved'] = false;
                $data['hidden'] = false;
                break;
            case 'hide':
                $data['hidden'] = true;
                break;
            case 'unhide':
                $data['hidden'] = false;
                break;
        }
        $tourReview->update($data);

        return redirect()->route('admin.tour-reviews.show', $tourReview)->with('success', 'Tour review updated.');
    }
}
