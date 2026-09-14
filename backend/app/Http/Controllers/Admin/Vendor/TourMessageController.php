<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TourBooking;
use App\Models\TourProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourMessageController extends Controller
{
    protected function myBookingsQuery()
    {
        $providerIds = TourProvider::where('vendor_id', auth()->id())->pluck('id');

        return TourBooking::whereIn('provider_id', $providerIds);
    }

    public function index(): View
    {
        $bookings = $this->myBookingsQuery()
            ->whereHas('messages')
            ->with(['tour', 'customer', 'messages'])
            ->latest()
            ->paginate(15);

        return view('admin.vendor.tour-messages.index', compact('bookings'));
    }

    public function show(string $uuid): View
    {
        $booking = $this->myBookingsQuery()
            ->where('uuid', $uuid)
            ->with(['tour', 'customer', 'messages.sender'])
            ->firstOrFail();

        return view('admin.vendor.tour-messages.show', compact('booking'));
    }

    public function reply(Request $request, string $uuid): RedirectResponse
    {
        $booking = $this->myBookingsQuery()->where('uuid', $uuid)->firstOrFail();
        $validated = $request->validate([
            'body' => 'required|string|min:1|max:2000',
        ]);
        $booking->messages()->create([
            'sender_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        return redirect()->route('admin.vendor.tour-messages.show', $booking->uuid)->with('success', __('admin.vendor.tour_messages.flash.reply_sent'));
    }
}
