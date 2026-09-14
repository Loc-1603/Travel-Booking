<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TourAvailabilitySlot;
use App\Models\TourProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourSlotController extends Controller
{
    public function index(Request $request, TourProduct $tour): View
    {
        $this->authorize('update', $tour);
        $query = $tour->slots()->orderBy('date')->orderBy('start_time');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $slots = $query->paginate(30)->withQueryString();

        return view('admin.vendor.tours.slots', compact('tour', 'slots'));
    }

    public function store(Request $request, TourProduct $tour): RedirectResponse
    {
        $this->authorize('update', $tour);
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'nullable|in:available,blocked',
            'price_override' => 'nullable|numeric|min:0',
        ]);
        $validated['status'] ??= 'available';
        if (empty($validated['price_override'])) {
            $validated['price_override'] = null;
        }

        TourAvailabilitySlot::updateOrCreate(
            [
                'tour_id' => $tour->id,
                'date' => $validated['date'],
                'start_time' => $validated['start_time'],
            ],
            [
                'end_time' => $validated['end_time'],
                'status' => $validated['status'],
                'price_override' => $validated['price_override'],
            ]
        );

        return redirect()->route('admin.vendor.tours.slots', $tour)->with('success', __('admin.vendor.tours.slots.flash.saved'));
    }

    public function destroy(TourProduct $tour, TourAvailabilitySlot $slot): RedirectResponse
    {
        $this->authorize('update', $tour);
        if ($slot->tour_id !== $tour->id) {
            abort(404);
        }
        if ($slot->status === 'booked') {
            return redirect()->route('admin.vendor.tours.slots', $tour)->with('error', __('admin.vendor.tours.slots.flash.remove_booked'));
        }
        $slot->delete();

        return redirect()->route('admin.vendor.tours.slots', $tour)->with('success', __('admin.vendor.tours.slots.flash.removed'));
    }
}
