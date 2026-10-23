<?php

namespace App\Providers;

use App\Events\BookingDisputeCreated;
use App\Events\PaymentConfirmed;
use App\Events\SupportTicketReplyCreated;
use App\Listeners\SendBookingConfirmationNotification;
use App\Listeners\SendBookingDisputeAdminNotification;
use App\Listeners\SendSupportTicketReplyNotification;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\BookingRoomRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\HotelRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\RoomAvailabilityRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\TourAvailabilitySlotRepositoryInterface;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Repositories\Contracts\TourDisputeRepositoryInterface;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use App\Repositories\Contracts\TourProductRepositoryInterface;
use App\Repositories\Contracts\TourProviderRepositoryInterface;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;
use App\Repositories\Eloquent\EloquentBookingRepository;
use App\Repositories\Eloquent\EloquentBookingRoomRepository;
use App\Repositories\Eloquent\EloquentCouponRepository;
use App\Repositories\Eloquent\EloquentHotelRepository;
use App\Repositories\Eloquent\EloquentPaymentRepository;
use App\Repositories\Eloquent\EloquentRoomAvailabilityRepository;
use App\Repositories\Eloquent\EloquentRoomRepository;
use App\Repositories\Eloquent\EloquentTourAvailabilitySlotRepository;
use App\Repositories\Eloquent\EloquentTourBookingRepository;
use App\Repositories\Eloquent\EloquentTourDisputeRepository;
use App\Repositories\Eloquent\EloquentTourPaymentRepository;
use App\Repositories\Eloquent\EloquentTourProductRepository;
use App\Repositories\Eloquent\EloquentTourProviderRepository;
use App\Repositories\Eloquent\EloquentWebhookEventRepository;
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
        $this->app->bind(BookingRepositoryInterface::class, EloquentBookingRepository::class);
        $this->app->bind(BookingRoomRepositoryInterface::class, EloquentBookingRoomRepository::class);
        $this->app->bind(RoomRepositoryInterface::class, EloquentRoomRepository::class);
        $this->app->bind(RoomAvailabilityRepositoryInterface::class, EloquentRoomAvailabilityRepository::class);
        $this->app->bind(HotelRepositoryInterface::class, EloquentHotelRepository::class);
        $this->app->bind(CouponRepositoryInterface::class, EloquentCouponRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->bind(WebhookEventRepositoryInterface::class, EloquentWebhookEventRepository::class);
        $this->app->bind(TourBookingRepositoryInterface::class, EloquentTourBookingRepository::class);
        $this->app->bind(TourAvailabilitySlotRepositoryInterface::class, EloquentTourAvailabilitySlotRepository::class);
        $this->app->bind(TourPaymentRepositoryInterface::class, EloquentTourPaymentRepository::class);
        $this->app->bind(TourProductRepositoryInterface::class, EloquentTourProductRepository::class);
        $this->app->bind(TourDisputeRepositoryInterface::class, EloquentTourDisputeRepository::class);
        $this->app->bind(TourProviderRepositoryInterface::class, EloquentTourProviderRepository::class);
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
