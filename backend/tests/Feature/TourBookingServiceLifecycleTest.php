<?php

use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use App\Services\TourBookingService;
use Carbon\Carbon;

beforeEach(function (): void {
    $vendor = User::create([
        'name' => 'Lifecycle Vendor', 'email' => 'lifecycle-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Lifecycle Customer', 'email' => 'lifecycle-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Ha Giang', 'slug' => 'ha-giang-lifecycle',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $vendor->id, 'business_name' => 'Lifecycle Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Lifecycle tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
});

/**
 * @return array{0: TourBooking, 1: TourAvailabilitySlot}
 */
function makeLifecycleTourBooking(object $ctx, string $status, Carbon $startAt, Carbon $endAt, ?Carbon $createdAt = null): array
{
    static $sequence = 0;
    $sequence++;

    $slot = TourAvailabilitySlot::create([
        'tour_id' => $ctx->tour->id,
        'date' => $startAt->copy()->toDateString(),
        'start_time' => sprintf('%02d:00:00', 7 + $sequence),
        'end_time' => sprintf('%02d:00:00', 8 + $sequence),
        'status' => 'booked',
    ]);

    $booking = TourBooking::create([
        'customer_id' => $ctx->customer->id, 'tour_id' => $ctx->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $ctx->tour->provider_id,
        'province_id' => $ctx->tour->province_id, 'start_at' => $startAt,
        'end_at' => $endAt, 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => $status,
    ]);

    $slot->update(['tour_booking_id' => $booking->id]);

    if ($createdAt !== null) {
        $booking->created_at = $createdAt;
        $booking->save();
    }

    return [$booking, $slot];
}

test('service expires pending bookings and releases their slots', function (): void {
    $ttl = (int) config('tour.hold_ttl_minutes', 15);
    [$expired, $expiredSlot] = makeLifecycleTourBooking(
        $this,
        TourBookingStatus::PENDING_PAYMENT->value,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(3),
        Carbon::now()->subMinutes($ttl + 5),
    );
    [$fresh] = makeLifecycleTourBooking(
        $this,
        TourBookingStatus::PENDING_PAYMENT->value,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(3),
    );

    $service = app(TourBookingService::class);

    expect($service->expirePendingBookings())->toBe(1)
        ->and($expired->fresh()->status)->toBe(TourBookingStatus::CANCELLED->value)
        ->and($expiredSlot->fresh()->status)->toBe('available')
        ->and($expiredSlot->fresh()->tour_booking_id)->toBeNull()
        ->and($fresh->fresh()->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value);

    expect($service->expirePendingBookings())->toBe(0);
});

test('service advances timed booking statuses', function (): void {
    [$ongoing] = makeLifecycleTourBooking(
        $this,
        TourBookingStatus::CONFIRMED->value,
        Carbon::now()->subHour(),
        Carbon::now()->addHour(),
    );
    [$completed] = makeLifecycleTourBooking(
        $this,
        TourBookingStatus::ONGOING->value,
        Carbon::now()->subHours(3),
        Carbon::now()->subHour(),
    );
    [$future] = makeLifecycleTourBooking(
        $this,
        TourBookingStatus::CONFIRMED->value,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(3),
    );

    $result = app(TourBookingService::class)->advanceTimedStatuses();

    expect($result)->toBe(['ongoing' => 1, 'completed' => 1])
        ->and($ongoing->fresh()->status)->toBe(TourBookingStatus::ONGOING->value)
        ->and($completed->fresh()->status)->toBe(TourBookingStatus::COMPLETED->value)
        ->and($future->fresh()->status)->toBe(TourBookingStatus::CONFIRMED->value);
});
