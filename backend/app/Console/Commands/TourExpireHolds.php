<?php

namespace App\Console\Commands;

use App\Enums\TourSlotStatus;
use App\Models\TourAvailabilitySlot;
use Illuminate\Console\Command;

class TourExpireHolds extends Command
{
    protected $signature = 'tour:expire-holds';

    protected $description = 'Release tour slots whose hold TTL expired (held -> available)';

    public function handle(): int
    {
        $count = TourAvailabilitySlot::where('status', TourSlotStatus::HELD->value)
            ->whereNotNull('held_until')
            ->where('held_until', '<', now())
            ->update(['status' => TourSlotStatus::AVAILABLE->value, 'held_until' => null, 'tour_booking_id' => null]);

        $this->info("Released {$count} expired tour slot hold(s).");

        return self::SUCCESS;
    }
}
