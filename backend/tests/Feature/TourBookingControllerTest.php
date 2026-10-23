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
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $this->vendor = User::create([
        'name' => 'Controller Vendor', 'email' => 'controller-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Controller Customer', 'email' => 'controller-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->otherCustomer = User::create([
        'name' => 'Other Controller Customer', 'email' => 'controller-other@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);

    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang Ctrl', 'slug' => 'ha-giang-ctrl',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Ctrl Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Ctrl tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
    $this->slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
});

test('customer index lists own tour bookings', function (): void {
    $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();

    $response = $this->actingAs($this->customer)->getJson('/api/v1/tour-bookings')->assertOk();

    expect($response->json('data.data'))->not->toBeEmpty()
        ->and((int) $response->json('data.meta.total'))->toBeGreaterThanOrEqual(1);
});

test('customer show returns own booking', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    $this->actingAs($this->customer)->getJson('/api/v1/tour-bookings/'.$uuid)
        ->assertOk()
        ->assertJsonPath('data.uuid', $uuid);
});

test('customer show is forbidden for another customer', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    $this->actingAs($this->otherCustomer)->getJson('/api/v1/tour-bookings/'.$uuid)
        ->assertForbidden();
});
test('checkout session creates payment for pending booking', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$uuid.'/checkout-session')
        ->assertOk()
        ->assertJsonPath('success', true);

    TourBooking::where('uuid', $uuid)->update(['status' => TourBookingStatus::CONFIRMED->value]);
    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$uuid.'/checkout-session')
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'This booking is not awaiting payment.',
            'code' => 'INVALID_STATUS',
        ]);
});

test('checkout endpoint keeps checkout failure contract', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    config([
        'services.vnpay.tmn_code' => null,
        'services.vnpay.hash_secret' => null,
    ]);

    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$uuid.'/checkout-session')
        ->assertStatus(500)
        ->assertJson([
            'success' => false,
            'code' => 'CHECKOUT_FAILED',
        ]);
});

test('cancel endpoint cancels pending booking', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$uuid.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', TourBookingStatus::CANCELLED->value);
});

test('dispute endpoint opens dispute for confirmed booking', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    TourBooking::where('uuid', $uuid)->update(['status' => TourBookingStatus::CONFIRMED->value]);

    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$uuid.'/dispute', [
            'customer_notes' => 'The guide did not show up at the meeting point.',
        ])
        ->assertCreated();

    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$uuid.'/dispute', [
            'customer_notes' => 'Trying to open another dispute for same booking.',
        ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'code' => 'DISPUTE_EXISTS',
        ]);
});

test('dispute endpoint rejects pending booking', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$uuid.'/dispute', [
            'customer_notes' => 'Dispute should not be allowed before payment.',
        ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'code' => 'INVALID_STATUS',
        ]);
});

test('invoice endpoint returns html for owner', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    $response = $this->actingAs($this->customer)
        ->get('/api/v1/tour-bookings/'.$uuid.'/invoice')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/html');
});
