<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TourDispute;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourDisputeController extends Controller
{
    public function __construct(
        protected RefundService $refunds,
    ) {}
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
                foreach ($booking->payments as $payment) {
                    if (! in_array($payment->status, [PaymentStatus::COMPLETED->value, PaymentStatus::REFUNDED->value], true)) {
                        continue;
                    }

                    $remaining = round((float) $payment->amount - (float) ($payment->refunded_amount ?? 0), 2);
                    if ($remaining <= 0) {
                        continue;
                    }

                    $this->refunds->request(
                        $payment,
                        $remaining,
                        'Tour dispute #'.$tourDispute->id,
                        'tour-dispute:'.$tourDispute->id.':'.$payment->id,
                        auth()->id(),
                    );
                }

                $booking->refresh()->load('payments');
                $fullyCovered = $booking->payments->isNotEmpty()
                    && $booking->payments->every(
                        fn ($payment) => $payment->status !== PaymentStatus::COMPLETED->value
                            || $this->refunds->fullyReserved($payment)
                    );

                if ($booking->payments()->where('status', PaymentStatus::REFUNDED->value)->exists() || $fullyCovered) {
                    $booking->update(['status' => TourBookingStatus::REFUNDED->value]);
                }
            }

            return redirect()->route('admin.tour-disputes.show', array_merge([$tourDispute], request()->query()))->with('success', __('admin.vendor.tour_disputes.flash.updated_refunded'));
        }

        return redirect()->route('admin.tour-disputes.show', array_merge([$tourDispute], request()->query()))->with('success', __('admin.vendor.tour_disputes.flash.updated'));
    }
}
