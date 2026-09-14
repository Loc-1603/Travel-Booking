<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TourBooking;
use App\Models\TourProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TourBookingController extends Controller
{
    public function index(Request $request): View
    {
        $providerIds = TourProvider::where('vendor_id', auth()->id())->pluck('id');
        $query = TourBooking::whereIn('provider_id', $providerIds)
            ->with(['tour', 'slot', 'customer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('start_at', '<=', $request->to);
        }

        $bookings = $query->latest()->paginate(15)->withQueryString();

        return view('admin.vendor.tour-bookings.index', compact('bookings'));
    }

    /**
     * View/download invoice for a tour booking (vendor's provider only).
     */
    public function invoice(string $uuid): Response
    {
        $booking = TourBooking::where('uuid', $uuid)
            ->with(['tour.provider', 'tour.province', 'slot', 'customer'])
            ->firstOrFail();
        $providerIds = TourProvider::where('vendor_id', auth()->id())->pluck('id');
        if (! $providerIds->contains($booking->provider_id)) {
            abort(403, __('admin.vendor.tour_bookings.flash.forbidden_invoice'));
        }

        $html = view('invoice.tour_booking', ['booking' => $booking])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="invoice-'.$booking->uuid.'.html"',
        ]);
    }
}
