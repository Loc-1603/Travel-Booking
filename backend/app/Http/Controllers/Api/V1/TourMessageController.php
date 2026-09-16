<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\TourMessageSent;
use App\Http\Requests\Api\StoreTourMessageRequest;
use App\Http\Resources\TourMessageResource;
use App\Models\TourBooking;
use App\Models\TourMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourMessageController extends BaseApiController
{
    /**
     * List messages of a tour booking (customer or provider's vendor only).
     * Chỉ mở sau khi đã thanh toán (confirmed/ongoing/completed).
     * Also marks messages from the other party as read.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $booking = TourBooking::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $booking);

        if (! in_array($booking->status, ['confirmed', 'ongoing', 'completed'], true)) {
            return $this->error('Chat is available only after payment is completed.', 403, 'PAYMENT_REQUIRED');
        }

        TourMessage::where('tour_booking_id', $booking->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = TourMessage::where('tour_booking_id', $booking->id)
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        return $this->success(TourMessageResource::collection($messages));
    }

    /**
     * Send a message in a tour booking thread. Broadcasts over socket + REST fallback.
     * Chỉ mở sau khi đã thanh toán (confirmed/ongoing/completed).
     */
    public function store(StoreTourMessageRequest $request, string $uuid): JsonResponse
    {
        $booking = TourBooking::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $booking);

        if (! in_array($booking->status, ['confirmed', 'ongoing', 'completed'], true)) {
            return $this->error('Chat is available only after payment is completed.', 403, 'PAYMENT_REQUIRED');
        }

        $message = TourMessage::create([
            'tour_booking_id' => $booking->id,
            'sender_id' => $request->user()->id,
            'body' => trim($request->input('body')),
        ]);

        // Socket is best-effort: REST response must succeed even if Reverb is down.
        try {
            broadcast(new TourMessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Tour chat broadcast failed, REST fallback only', [
                'message_id' => $message->id, 'error' => $e->getMessage(),
            ]);
        }

        return $this->success(new TourMessageResource($message->load('sender')), 201);
    }
}
