<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Events\TourMessageInboxUpdated;
use App\Events\TourMessageRead;
use App\Events\TourMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\TourMessageResource;
use App\Models\TourBooking;
use App\Models\TourMessage;
use App\Models\TourProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $booking = $this->myBookingsQuery()->where('uuid', $uuid)->firstOrFail();

        // Read-only: never auto-marks as read. The show page JS calls read()
        // explicitly after a user interaction (activity-gated read receipts).
        $booking->load(['tour', 'customer', 'messages.sender']);

        return view('admin.vendor.tour-messages.show', compact('booking'));
    }

    /**
     * Activity-gated read receipt for vendors. Called via AJAX after the
     * vendor interacts with the page while the thread is open.
     */
    public function read(string $uuid): JsonResponse
    {
        $booking = $this->myBookingsQuery()->where('uuid', $uuid)->firstOrFail();

        $readUntil = now();
        $marked = TourMessage::where('tour_booking_id', $booking->id)
            ->where('sender_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => $readUntil]);

        // Socket is best-effort: the JSON response must succeed even if Reverb is down.
        if ($marked > 0) {
            try {
                $last = TourMessage::where('tour_booking_id', $booking->id)
                    ->with('sender')
                    ->orderBy('created_at')
                    ->get()
                    ->last();
                broadcast(new TourMessageRead(auth()->id(), $booking->uuid, $readUntil->toIso8601String()))->toOthers();
                broadcast(new TourMessageInboxUpdated(
                    auth()->id(),
                    $booking->uuid,
                    $last ? [
                        'body' => $last->body,
                        'created_at' => $last->created_at?->toIso8601String(),
                        'sender_name' => $last->sender?->name,
                    ] : ['body' => null, 'created_at' => null, 'sender_name' => null],
                    TourMessage::unreadForVendor(auth()->id()),
                ))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Tour message read broadcast failed (admin read)', [
                    'booking_uuid' => $booking->uuid, 'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'marked' => $marked,
                'read_until' => $readUntil->toIso8601String(),
            ],
        ]);
    }

    public function reply(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $booking = $this->myBookingsQuery()->where('uuid', $uuid)->firstOrFail();
        $validated = $request->validate([
            'body' => 'required|string|min:1|max:2000',
        ]);
        $message = $booking->messages()->create([
            'sender_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        // Socket is best-effort: the response must succeed even if Reverb is down.
        try {
            broadcast(new TourMessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Tour chat broadcast failed (vendor reply), REST fallback only', [
                'message_id' => $message->id, 'error' => $e->getMessage(),
            ]);
        }

        // AJAX (vendor thread page) expects JSON; classic form posts keep redirect.
        if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $message->loadMissing('sender');

            return response()->json([
                'success' => true,
                'data' => (new TourMessageResource($message))->toArray($request),
            ], 201);
        }

        return redirect()->route('admin.vendor.tour-messages.show', $booking->uuid)->with('success', __('admin.vendor.tour_messages.flash.reply_sent'));
    }
}
