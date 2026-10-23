<?php

namespace App\Console\Commands;

use App\Services\TourBookingService;
use Illuminate\Console\Command;

class TourAutoComplete extends Command
{
    protected $signature = 'tour:auto-complete';

    protected $description = 'Advance tour bookings by time (confirmed -> ongoing -> completed)';

    public function handle(TourBookingService $bookingService): int
    {
        $result = $bookingService->advanceTimedStatuses();

        $this->info("Advanced {$result['ongoing']} tour booking(s) to ongoing, {$result['completed']} to completed.");

        return self::SUCCESS;
    }
}
