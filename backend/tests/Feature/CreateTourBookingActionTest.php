<?php

use App\Actions\CreateTourBookingAction;
use App\DTOs\CreateTourBookingData;
use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Tour Vendor', 'email' => 'tour-action-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Tour Customer', 'email' => 'tour-action-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang', 'slug' => 'ha-giang-action',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Test Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Test 1vs1 tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
    $this->slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
});

test('action creates tour booking and locks slot', function (): void {
    $data = new CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $this->slot->id,
        pricingMode: 'hour',
        durationValue: 4,
    );

    $booking = app(CreateTourBookingAction::class)->handle($data);

    expect($booking->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value)
        ->and((float) $booking->total_price)->toBeGreaterThan(0);

    expect(TourAvailabilitySlot::find($this->slot->id)->status)->toBe('booked');
});

test('action rejects slot from another tour', function (): void {
    $otherTour = TourProduct::create([
        'provider_id' => $this->tour->provider_id, 'province_id' => $this->tour->province_id,
        'title' => 'Other tour', 'base_fixed' => 100000,
        'base_price_hourly' => 50000, 'base_price_daily' => 400000,
        'status' => 'published',
    ]);
    $otherSlot = TourAvailabilitySlot::create([
        'tour_id' => $otherTour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '14:00:00', 'end_time' => '16:00:00', 'status' => 'available',
    ]);

    $data = new CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $otherSlot->id,
        pricingMode: 'hour',
        durationValue: 2,
    );

    expect(fn () => app(CreateTourBookingAction::class)->handle($data))
        ->toThrow(InvalidArgumentException::class, 'Slot does not belong');

    expect(TourBooking::count())->toBe(0);
});

test('action fails when tour is not published', function (): void {
    $this->tour->update(['status' => 'draft']);

    $data = new CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $this->slot->id,
        pricingMode: 'hour',
        durationValue: 2,
    );

    expect(fn () => app(CreateTourBookingAction::class)->handle($data))
        ->toThrow(RuntimeException::class, 'Tour is not available');

    expect(TourBooking::count())->toBe(0);
});

test('action fails when slot is already booked', function (): void {
    $this->slot->update(['status' => 'booked']);

    $data = new CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $this->slot->id,
        pricingMode: 'hour',
        durationValue: 2,
    );

    expect(fn () => app(CreateTourBookingAction::class)->handle($data))
        ->toThrow(RuntimeException::class, 'no longer available');

    expect(TourBooking::count())->toBe(0);
});
