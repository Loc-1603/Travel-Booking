<?php

use App\Actions\CreateTourVnpayCheckoutAction;
use App\DTOs\TourCheckoutData;
use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourPayment;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $this->customer = User::create([
        'name' => 'Checkout Customer', 'email' => 'tour-checkout-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $vendor = User::create([
        'name' => 'Checkout Vendor', 'email' => 'tour-checkout-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Ha Giang', 'slug' => 'ha-giang-checkout',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $vendor->id, 'business_name' => 'Checkout Guide', 'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Checkout tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'booked',
    ]);
    $this->booking = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $tour->id,
        'slot_id' => $slot->id, 'provider_id' => $provider->id,
        'province_id' => $province->id, 'start_at' => Carbon::tomorrow()->setTime(8, 0),
        'end_at' => Carbon::tomorrow()->setTime(10, 0), 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::PENDING_PAYMENT->value,
    ]);
});

test('action creates vnpay checkout for pending tour booking', function (): void {
    $result = app(CreateTourVnpayCheckoutAction::class)->handle(new TourCheckoutData(
        bookingId: $this->booking->id,
        ipAddr: '127.0.0.1',
    ));

    expect($result['checkout_url'])->toContain('vnp_TxnRef=tour_')
        ->and($result['txn_ref'])->toStartWith('tour_')
        ->and($result['payment_id'])->toBeInt();

    $payment = TourPayment::find($result['payment_id']);
    expect($payment)->not->toBeNull()
        ->and($payment->tour_booking_id)->toBe($this->booking->id)
        ->and($payment->status)->toBe(PaymentStatus::PENDING->value)
        ->and($payment->external_id)->toBe($result['txn_ref']);
});

test('action rejects non-pending tour booking', function (): void {
    $this->booking->update(['status' => TourBookingStatus::CONFIRMED->value]);

    expect(fn () => app(CreateTourVnpayCheckoutAction::class)->handle(
        new TourCheckoutData(bookingId: $this->booking->id)
    ))->toThrow(RuntimeException::class, 'This booking is not awaiting payment.');
});

test('action rejects missing tour booking', function (): void {
    expect(fn () => app(CreateTourVnpayCheckoutAction::class)->handle(
        new TourCheckoutData(bookingId: $this->booking->id + 999)
    ))->toThrow(InvalidArgumentException::class, 'Tour booking not found.');
});

test('checkout endpoint keeps the invalid status contract', function (): void {
    $this->booking->update(['status' => TourBookingStatus::CONFIRMED->value]);

    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$this->booking->uuid.'/checkout-session')
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'This booking is not awaiting payment.',
            'code' => 'INVALID_STATUS',
        ]);
});

test('checkout endpoint keeps the checkout failure contract', function (): void {
    config([
        'services.vnpay.tmn_code' => null,
        'services.vnpay.hash_secret' => null,
    ]);

    $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$this->booking->uuid.'/checkout-session')
        ->assertStatus(500)
        ->assertJson([
            'success' => false,
            'code' => 'CHECKOUT_FAILED',
        ]);
});
