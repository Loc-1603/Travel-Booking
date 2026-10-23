<?php

use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Lifecycle Vendor', 'email' => 'lifecycle-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Lifecycle Customer', 'email' => 'lifecycle-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Lifecycle Province', 'slug' => 'lifecycle-province',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Lifecycle Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Lifecycle tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
});

test('tour-bookings:expire-pending cancels overdue booking', function (): void {
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
    $storeResponse = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $storeResponse->json('data.booking.uuid');
    $booking = TourBooking::where('uuid', $uuid)->firstOrFail();

    TourBooking::where('id', $booking->id)->update([
        'created_at' => now()->subMinutes((int) config('tour.hold_ttl_minutes', 15) + 5),
    ]);

    $this->artisan('tour-bookings:expire-pending')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(TourBookingStatus::CANCELLED->value)
        ->and($slot->fresh()->status)->toBe('available');
});

test('tour:expire-holds releases only expired holds', function (): void {
    $expired = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00',
        'status' => 'held', 'held_until' => now()->subMinutes(5),
    ]);
    $fresh = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '10:00:00', 'end_time' => '12:00:00',
        'status' => 'held', 'held_until' => now()->addMinutes(10),
    ]);

    $this->artisan('tour:expire-holds')->assertSuccessful();

    expect($expired->fresh()->status)->toBe('available')
        ->and($fresh->fresh()->status)->toBe('held');
});

test('tour:auto-complete advances bookings by time', function (): void {
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::yesterday()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    $past = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->tour->provider_id,
        'province_id' => $this->tour->province_id,
        'start_at' => now()->subHours(3), 'end_at' => now()->subHour(),
        'pricing_mode' => 'hour', 'duration_value' => 2,
        'base_fixed' => 200000, 'unit_price' => 100000, 'subtotal' => 400000,
        'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);

    $this->artisan('tour:auto-complete')->assertSuccessful();

    expect($past->fresh()->status)->toBe(TourBookingStatus::COMPLETED->value);
});
