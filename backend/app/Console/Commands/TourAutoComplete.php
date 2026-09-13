<?php

namespace App\Console\Commands;

use App\Enums\TourBookingStatus;
use App\Models\TourBooking;
use Illuminate\Console\Command;

class TourAutoComplete extends Command
{
    protected $signature = 'tour:auto-complete';

    protected $description = 'Advance tour bookings by time (confirmed -> ongoing -> completed)';

    public function handle(): int
    {
        $now = now();

        $ongoing = TourBooking::where('status', TourBookingStatus::CONFIRMED->value)
            ->where('start_at', '<=', $now)
            ->update(['status' => TourBookingStatus::ONGOING->value]);

        $completed = TourBooking::where('status', TourBookingStatus::ONGOING->value)
            ->where('end_at', '<=', $now)
            ->update(['status' => TourBookingStatus::COMPLETED->value]);

        $this->info("Advanced {$ongoing} tour booking(s) to ongoing, {$completed} to completed.");

        return self::SUCCESS;
    }
}
