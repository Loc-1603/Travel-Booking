<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\RoomResource;
use App\Models\Hotel;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelAvailabilityController extends BaseApiController
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Rooms of a hotel with per-date availability (public).
     * Query: check_in, check_out (Y-m-d, default tomorrow / day after), adults, rooms.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $request->merge([
            'check_in' => $request->input('check_in', now()->addDay()->toDateString()),
        ]);
        if (! $request->filled('check_out')) {
            $request->merge([
                'check_out' => Carbon::parse($request->input('check_in'))->addDay()->toDateString(),
            ]);
        }

        $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'nullable|integer|min:1|max:20',
            'rooms' => 'nullable|integer|min:1|max:10',
        ]);

        $hotel = Hotel::query()
            ->where('id', $id)
            ->where('status', 'active')
            ->whereHas('vendor', fn ($q) => $q->whereHas('vendorProfile', fn ($q2) => $q2->where('status', 'approved')))
            ->first();
        if (! $hotel) {
            return $this->error('Hotel not found.', 404, 'NOT_FOUND');
        }

        $minQuantity = (int) $request->input('rooms', 1);
        $roomIds = $this->availabilityService->getRoomIdsWithAvailability(
            $request->input('check_in'),
            $request->input('check_out'),
            $minQuantity
        );

        $rooms = $hotel->rooms()
            ->with(['hotel', 'images', 'amenities', 'roomType'])
            ->whereIn('id', $roomIds)
            ->get();

        return $this->success(RoomResource::collection($rooms));
    }
}
