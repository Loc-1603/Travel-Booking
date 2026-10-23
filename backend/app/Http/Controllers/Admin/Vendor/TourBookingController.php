<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Repositories\Contracts\TourProviderRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TourBookingController extends \App\Http\Controllers\Controller
{
    public function __construct(
        protected TourBookingRepositoryInterface $bookings,
        protected TourProviderRepositoryInterface $providers,
    ) {}

    public function index(Request $request): View
    {
        $providerIds = $this->providers->providerIdsForVendor((int) auth()->id());

        $bookings = $this->bookings->paginateForProviders(
            $providerIds->all(),
            $request->input('status'),
            $request->input('from'),
            $request->input('to'),
            15,
        );

        return view('admin.vendor.tour-bookings.index', compact('bookings'));
    }

    /**
     * View/download invoice for a tour booking (vendor's provider only).
     */
    public function invoice(string $uuid): Response
    {
        $booking = $this->bookings->findByUuidOrFail($uuid, ['tour.provider', 'tour.province', 'slot', 'customer']);

        $providerIds = $this->providers->providerIdsForVendor((int) auth()->id());
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
