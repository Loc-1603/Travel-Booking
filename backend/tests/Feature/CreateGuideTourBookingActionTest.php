<?php

use App\Actions\CreateGuideTourBookingAction;
use App\DTOs\CreateGuideTourBookingData;
use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;
use RuntimeException;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Guide Vendor', 'email' => 'guide-action-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Guide Customer', 'email' => 'guide-action-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang', 'slug' => 'ha-giang-guide-action',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $this->provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Test Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $this->provider->id, 'province_id' => $province->id,
        'title' => 'Test 1vs1 tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
    $this->slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
});

test('action creates guide booking', function (): void {
    $data = new CreateGuideTourBookingData(
        customerId: $this->customer->id,
        providerUuid: $this->provider->uuid,
        travelDate: Carbon::tomorrow()->toDateString(),
    );

    $booking = app(CreateGuideTourBookingAction::class)->handle($data);

    expect($booking->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value)
        ->and($booking->pricing_mode)->toBe('day')
        ->and((int) $booking->duration_value)->toBe(1);
});

test('action fails when guide has no free slot on travel date', function (): void {
    $data = new CreateGuideTourBookingData(
        customerId: $this->customer->id,
        providerUuid: $this->provider->uuid,
        travelDate: Carbon::today()->addDays(30)->toDateString(),
    );

    expect(fn () => app(CreateGuideTourBookingAction::class)->handle($data))
        ->toThrow(RuntimeException::class, 'Guide is not free');
});

test('action fails when slot does not belong to guide', function (): void {
    $otherVendor = User::create([
        'name' => 'Other Vendor', 'email' => 'guide-other-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $otherProvider = TourProvider::create([
        'vendor_id' => $otherVendor->id, 'business_name' => 'Other Guide', 'status' => 'approved',
    ]);
    // reuse tour? create slot under other provider's tour
    $country = \App\Models\Country::first();
    $province = \App\Models\TourProvince::first();
    $otherTour = TourProduct::create([
        'provider_id' => $otherProvider->id, 'province_id' => $province->id,
        'title' => 'Other tour', 'base_fixed' => 100000,
        'base_price_hourly' => 50000, 'base_price_daily' => 400000,
        'status' => 'published',
    ]);
    $otherSlot = TourAvailabilitySlot::create([
        'tour_id' => $otherTour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '14:00:00', 'end_time' => '16:00:00', 'status' => 'available',
    ]);

    $data = new CreateGuideTourBookingData(
        customerId: $this->customer->id,
        providerUuid: $this->provider->uuid,
        slotId: $otherSlot->id,
        travelDate: Carbon::tomorrow()->toDateString(),
    );

    expect(fn () => app(CreateGuideTourBookingAction::class)->handle($data))
        ->toThrow(RuntimeException::class, 'does not belong to this guide');
});

test('action creates guide booking with explicit slot', function (): void {
    $data = new CreateGuideTourBookingData(
        customerId: $this->customer->id,
        providerUuid: $this->provider->uuid,
        slotId: $this->slot->id,
        travelDate: Carbon::tomorrow()->toDateString(),
    );

    $booking = app(CreateGuideTourBookingAction::class)->handle($data);

    expect($booking->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value);
});
