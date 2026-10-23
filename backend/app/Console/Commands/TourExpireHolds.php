<?php

namespace App\Console\Commands;

use App\Services\TourAvailabilityService;
use Illuminate\Console\Command;

class TourExpireHolds extends Command
{
    protected $signature = 'tour:expire-holds';

    protected $description = 'Release tour slots whose hold TTL expired (held -> available)';

    public function handle(TourAvailabilityService $availabilityService): int
    {
        $count = $availabilityService->releaseExpiredHolds();

        $this->info("Released {$count} expired tour slot hold(s).");

        return self::SUCCESS;
    }
}
