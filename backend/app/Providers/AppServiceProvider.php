<?php

namespace App\Providers;

use App\Events\BookingDisputeCreated;
use App\Events\PaymentConfirmed;
use App\Events\SupportTicketReplyCreated;
use App\Listeners\SendBookingConfirmationNotification;
use App\Listeners\SendBookingDisputeAdminNotification;
use App\Listeners\SendSupportTicketReplyNotification;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        // API (React SPA) broadcast auth under /api/v1/broadcasting/auth (Bearer token).
        Broadcast::routes(['prefix' => 'api/v1', 'middleware' => ['auth:sanctum']]);

        Event::listen(SupportTicketReplyCreated::class, SendSupportTicketReplyNotification::class);
        Event::listen(PaymentConfirmed::class, SendBookingConfirmationNotification::class);
        Event::listen(BookingDisputeCreated::class, SendBookingDisputeAdminNotification::class);

        $this->checkStorageLink();
    }

    /**
     * Health check for public/storage symlink.
     */
    protected function checkStorageLink(): void
    {
        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');

        if (! file_exists($linkPath) || ! is_dir($linkPath)) {
            Log::warning('Storage link check failed', [
                'link_path' => $linkPath,
                'exists' => file_exists($linkPath),
                'is_dir' => is_dir($linkPath),
                'target_path' => $targetPath,
            ]);

            return;
        }

        // On Windows, symlink may be reported as directory; still verify target reachable
        if (is_link($linkPath)) {
            $real = realpath($linkPath);
            if ($real === false || ! str_starts_with($real, $targetPath)) {
                Log::warning('Storage symlink points to unexpected location', [
                    'link_path' => $linkPath,
                    'real_path' => $real,
                    'expected_target' => $targetPath,
                ]);
            }
        }
    }
}
