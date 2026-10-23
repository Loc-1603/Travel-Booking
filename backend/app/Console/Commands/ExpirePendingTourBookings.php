<?php

namespace App\Console\Commands;

use App\Services\TourBookingService;
use Illuminate\Console\Command;

class ExpirePendingTourBookings extends Command
{
    protected $signature = 'tour-bookings:expire-pending';

    protected $description = 'Cancel pending tour bookings past expires_at and release their slots';

    public function handle(TourBookingService $bookingService): int
    {
        $count = $bookingService->expirePendingBookings();

        $this->info("Expired {$count} pending tour booking(s).");

        return self::SUCCESS;
    }
}
