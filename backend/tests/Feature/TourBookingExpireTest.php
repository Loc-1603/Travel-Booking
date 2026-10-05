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
        'name' => 'Expire Vendor', 'email' => 'expire-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Expire Customer', 'email' => 'expire-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang Expire', 'slug' => 'ha-giang-expire-test',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Expire Guide',
        'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Expire test tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
    $this->slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
});

test('guest can preview tour price without login (BE-1.4)', function (): void {
    $this->postJson('/api/v1/tour-bookings/preview', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 4,
    ])->assertOk()
        ->assertJsonPath('data.subtotal', 900000)
        ->assertJsonPath('data.total', 1188000)
        ->assertJsonPath('data.currency', 'VND');
});

test('guest can preview guide price without login (BE-1.4)', function (): void {
    $this->postJson('/api/v1/tour-bookings/guide-preview', [
        'provider_uuid' => $this->tour->provider->uuid,
        'travel_date' => Carbon::tomorrow()->toDateString(),
    ])->assertOk()
        ->assertJsonPath('data.pricing_mode', 'day')
        ->assertJsonPath('data.subtotal', 1500000);
});

test('booking resource exposes expires_at can_pay is_expired and slot held_until (BE-1.1/BE-1.2)', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();

    $bookingJson = $store->json('data.booking');
    expect($bookingJson['expires_at'] ?? null)->not->toBeNull()
        ->and($bookingJson['is_expired'] ?? null)->toBeFalse()
        ->and($bookingJson['can_pay'] ?? null)->toBeTrue();

    // Slot resource luôn có key held_until (null sau khi BOOKED).
    expect(array_key_exists('held_until', $bookingJson['slot'] ?? []))->toBeTrue();
});

test('expired pending booking shows is_expired and can_pay false', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    TourBooking::where('uuid', $uuid)->update([
        'created_at' => now()->subMinutes((int) config('tour.hold_ttl_minutes', 15) + 1),
    ]);

    $this->actingAs($this->customer)->getJson('/api/v1/tour-bookings/'.$uuid)
        ->assertOk()
        ->assertJsonPath('data.is_expired', true)
        ->assertJsonPath('data.can_pay', false);
});

test('expire command cancels overdue booking and releases slot, keeps fresh booking (BE-1.3)', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');
    $booking = TourBooking::where('uuid', $uuid)->firstOrFail();

    // Chưa quá hạn -> command không làm gì.
    $this->artisan('tour-bookings:expire-pending')->assertSuccessful();
    expect($booking->fresh()->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value)
        ->and($this->slot->fresh()->status)->toBe('booked');

    // Quá hạn -> cancel + release slot.
    TourBooking::where('id', $booking->id)->update(['created_at' => now()->subMinutes((int) config('tour.hold_ttl_minutes', 15) + 5)]);
    $this->artisan('tour-bookings:expire-pending')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(TourBookingStatus::CANCELLED->value)
        ->and($this->slot->fresh()->status)->toBe('available')
        ->and($this->slot->fresh()->tour_booking_id)->toBeNull();
});
