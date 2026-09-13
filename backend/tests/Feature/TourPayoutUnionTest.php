<?php

use App\Enums\TourBookingStatus;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Payout;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvince;
use App\Models\TourProvider;
use App\Models\User;
use App\Services\PayoutService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Payout Vendor', 'email' => 'payout-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Payout Customer', 'email' => 'payout-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
});

test('union payout merges hotel and tour bookings per vendor', function (): void {
    $hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Payout Hotel',
        'country_id' => $this->country->id, 'status' => 'active',
    ]);
    $checkIn = Carbon::tomorrow()->toDateString();
    Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $hotel->id,
        'status' => 'confirmed', 'check_in' => $checkIn,
        'check_out' => Carbon::tomorrow()->addDay()->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND',
    ]);

    $province = TourProvince::create([
        'country_id' => $this->country->id, 'name' => 'Huế', 'slug' => 'hue-payout',
        'description' => 'Test', 'is_featured' => false,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Payout Guide',
        'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Payout tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => $checkIn,
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $tour->id,
        'slot_id' => $slot->id, 'provider_id' => $provider->id,
        'province_id' => $province->id, 'start_at' => $checkIn.' 08:00:00',
        'end_at' => $checkIn.' 10:00:00', 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);

    $start = Carbon::today()->toDateString();
    $end = Carbon::today()->addDays(7)->toDateString();

    $created = app(PayoutService::class)->generateForPeriod($start, $end);

    expect($created)->toHaveCount(1);
    $payout = $created[0];
    // gross = 1000000 + 500000
    expect((float) $payout->amount)->toBe(1500000.0);
    expect($payout->bookings()->count())->toBe(1);
    expect($payout->tourBookings()->count())->toBe(1);
    expect(Payout::count())->toBe(1);

    // Idempotent: second run creates nothing (both pivots excluded)
    $again = app(PayoutService::class)->generateForPeriod($start, $end);
    expect($again)->toHaveCount(0);
});
