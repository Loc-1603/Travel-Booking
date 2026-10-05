<?php

namespace App\Console\Commands;

use App\Enums\TourBookingStatus;
use App\Enums\TourSlotStatus;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingTourBookings extends Command
{
    protected $signature = 'tour-bookings:expire-pending';

    protected $description = 'Cancel pending tour bookings past expires_at and release their slots';

    public function handle(): int
    {
        $ttl = (int) config('tour.hold_ttl_minutes', 15);
        $cutoff = now()->subMinutes($ttl);

        $expiredIds = TourBooking::where('status', TourBookingStatus::PENDING_PAYMENT->value)
            ->where('created_at', '<=', $cutoff)
            ->pluck('id');

        $count = 0;
        foreach ($expiredIds as $bookingId) {
            DB::transaction(function () use ($bookingId) {
                $booking = TourBooking::where('id', $bookingId)
                    ->where('status', TourBookingStatus::PENDING_PAYMENT->value)
                    ->lockForUpdate()
                    ->first();

                if (! $booking || ! $booking->isExpired()) {
                    return;
                }

                $slot = TourAvailabilitySlot::where('id', $booking->slot_id)->lockForUpdate()->first();
                if ($slot) {
                    $slot->update([
                        'status' => TourSlotStatus::AVAILABLE->value,
                        'held_until' => null,
                        'tour_booking_id' => null,
                    ]);
                }

                $booking->update(['status' => TourBookingStatus::CANCELLED->value]);
            });
            $count++;
        }

        $this->info("Expired {$count} pending tour booking(s).");

        return self::SUCCESS;
    }
}
