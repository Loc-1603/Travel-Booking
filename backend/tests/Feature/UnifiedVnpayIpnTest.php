<?php

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Events\PaymentConfirmed;
use App\Events\TourPaymentConfirmed;
use App\Models\Country;
use App\Models\Payment;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourPayment;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use App\Services\VnpayService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $this->vendor = User::create([
        'name' => 'Unified Vendor', 'email' => 'unified-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Unified Customer', 'email' => 'unified-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Huế', 'slug' => 'hue-unified',
        'description' => 'Test', 'is_featured' => false,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Unified Guide',
        'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Unified tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => now()->addDay()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    $this->tourBooking = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $tour->id,
        'slot_id' => $slot->id, 'provider_id' => $provider->id,
        'province_id' => $province->id, 'start_at' => now()->addDay()->setTime(8, 0),
        'end_at' => now()->addDay()->setTime(10, 0), 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => 'pending_payment',
    ]);
});

/** Build a signed IPN payload for a hotel or tour payment, like VNPay would send. */
function unifiedSignedIpn(string $txnRef, int $amountVnd, array $overrides = []): array
{
    $params = array_merge([
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_Amount' => $amountVnd * 100,
        'vnp_BankCode' => 'NCB',
        'vnp_CurrCode' => 'VND',
        'vnp_TxnRef' => $txnRef,
        'vnp_OrderInfo' => 'Thanh toan unified',
        'vnp_TransactionNo' => '14'.random_int(100000, 999999),
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_PayDate' => now()->format('YmdHis'),
    ], $overrides);
    $params['vnp_SecureHash'] = app(VnpayService::class)->signature($params);

    return $params;
}

function makeUnifiedTourPayment($booking, string $txnRef): TourPayment
{
    return TourPayment::create([
        'tour_booking_id' => $booking->id, 'amount' => 500000,
        'currency' => 'VND', 'provider' => 'vnpay', 'external_id' => $txnRef,
        'status' => PaymentStatus::PENDING->value,
    ]);
}

test('unified ipn confirms a tour payment and booking', function (): void {
    Event::fake([TourPaymentConfirmed::class]);

    $payment = makeUnifiedTourPayment($this->tourBooking, 'tour_unified-1');

    $response = $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query(
        unifiedSignedIpn('tour_unified-1', 500000)
    ));

    $response->assertOk()->assertJson(['RspCode' => '00']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($this->tourBooking->fresh()->status)->toBe(TourBookingStatus::CONFIRMED->value);

    Event::assertDispatched(TourPaymentConfirmed::class);
});

test('legacy tour ipn url still confirms tour payments', function (): void {
    $payment = makeUnifiedTourPayment($this->tourBooking, 'tour_legacy-1');

    $response = $this->getJson('/api/v1/payments/tour-vnpay-ipn?'.http_build_query(
        unifiedSignedIpn('tour_legacy-1', 500000)
    ));

    $response->assertOk()->assertJson(['RspCode' => '00']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($this->tourBooking->fresh()->status)->toBe(TourBookingStatus::CONFIRMED->value);
});

test('unified ipn rejects bad signature and unknown orders for tours', function (): void {
    $payment = makeUnifiedTourPayment($this->tourBooking, 'tour_unified-2');

    $bad = unifiedSignedIpn('tour_unified-2', 500000);
    $bad['vnp_SecureHash'] = 'deadbeef';
    $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query($bad))
        ->assertOk()->assertJson(['RspCode' => '97']);

    $missing = unifiedSignedIpn('tour_no-such-order', 500000);
    $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query($missing))
        ->assertOk()->assertJson(['RspCode' => '01']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PENDING->value)
        ->and($this->tourBooking->fresh()->status)->toBe('pending_payment');
});

test('failed tour ipn marks payment failed and keeps booking payable', function (): void {
    $payment = makeUnifiedTourPayment($this->tourBooking, 'tour_unified-3');

    $response = $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query(
        unifiedSignedIpn('tour_unified-3', 500000, [
            'vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02',
            'vnp_TransactionNo' => '14999998',
        ])
    ));

    $response->assertOk()->assertJson(['RspCode' => '00']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED->value)
        ->and($this->tourBooking->fresh()->status)->toBe('pending_payment');
});

test('replayed tour ipn is not processed twice', function (): void {
    $payment = makeUnifiedTourPayment($this->tourBooking, 'tour_unified-4');
    $query = http_build_query(unifiedSignedIpn('tour_unified-4', 500000));

    $this->getJson('/api/v1/payments/vnpay-ipn?'.$query)->assertOk()->assertJson(['RspCode' => '00']);
    $second = $this->getJson('/api/v1/payments/vnpay-ipn?'.$query)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($second->json('RspCode'))->toBeIn(['00', '02']);
});

test('hotel ipn resolves on either url (cross-table fallback)', function (): void {
    Event::fake([PaymentConfirmed::class]);

    $hotel = App\Models\Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Unified Hotel',
        'city' => 'Hà Nội', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $booking = App\Models\Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $hotel->id,
        'status' => App\Enums\BookingStatus::PENDING_PAYMENT->value,
        'check_in' => now()->addDays(7)->toDateString(),
        'check_out' => now()->addDays(9)->toDateString(),
        'total_price' => 1404000, 'currency' => 'VND',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id, 'amount' => 1404000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'hotel-fallback-1',
        'status' => PaymentStatus::PENDING->value,
    ]);

    // Hotel ref arriving at the legacy tour URL still resolves via fallback.
    $response = $this->getJson('/api/v1/payments/tour-vnpay-ipn?'.http_build_query(
        unifiedSignedIpn('hotel-fallback-1', 1404000)
    ));

    $response->assertOk()->assertJson(['RspCode' => '00']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($booking->fresh()->status)->toBe(App\Enums\BookingStatus::CONFIRMED->value);

    Event::assertDispatched(PaymentConfirmed::class);
});
