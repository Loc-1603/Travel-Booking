<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourDisputeController extends Controller
{
    public function index(Request $request): View
    {
        $query = TourDispute::with(['booking.customer', 'booking.tour']);
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $disputes = $query->latest()->paginate(15)->withQueryString();

        return view('admin.tour-disputes.index', compact('disputes'));
    }

    public function show(TourDispute $tourDispute): View
    {
        $tourDispute->load(['booking.customer', 'booking.tour', 'booking.provider', 'resolvedBy']);

        return view('admin.tour-disputes.show', compact('tourDispute'));
    }

    public function update(Request $request, TourDispute $tourDispute): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_review,resolved,closed',
            'internal_notes' => 'nullable|string|max:5000',
            'refund_payments' => 'nullable|boolean',
        ]);
        $refund = (bool) ($validated['refund_payments'] ?? false);
        unset($validated['refund_payments']);

        if (in_array($validated['status'], ['resolved', 'closed'], true)) {
            $validated['resolved_at'] = now();
            $validated['resolved_by'] = auth()->id();
        }
        $tourDispute->update($validated);

        if ($refund) {
            $booking = $tourDispute->booking;
            if ($booking) {
                $payments = app(\App\Services\TourPaymentService::class);
                foreach ($booking->payments as $payment) {
                    $payments->refundFull($payment->fresh(), 'Tour dispute #'.$tourDispute->id);
                }
                if ($booking->fresh()->payments()->where('status', \App\Enums\PaymentStatus::REFUNDED->value)->exists()) {
                    $booking->update(['status' => \App\Enums\TourBookingStatus::REFUNDED->value]);
                }
            }

            return redirect()->route('admin.tour-disputes.show', $tourDispute)->with('success', __('admin.vendor.tour_disputes.flash.updated_refunded'));
        }

        return redirect()->route('admin.tour-disputes.show', $tourDispute)->with('success', __('admin.vendor.tour_disputes.flash.updated'));
    }
}
