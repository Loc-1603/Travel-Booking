<?php

use App\Actions\CancelBookingAction;
use App\Actions\CancelTourBookingAction;
use App\Actions\CreateBookingAction;
use App\Actions\CreateTourBookingAction;
use App\DTOs\CreateBookingData;
use App\DTOs\CreateTourBookingData;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Room;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourPayment;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Services\PayoutService;
use App\Services\VnpayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'regression-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'services.vnpay.refund_enabled' => false,
    ]);

    $this->vendor = User::create([
        'name' => 'Regression Vendor', 'email' => 'regression-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Regression Customer', 'email' => 'regression-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Regression Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Regression Room',
        'capacity' => 2, 'base_price' => 500000, 'total_rooms' => 5,
    ]);
});

function regressionSignedIpn(string $txnRef, int $amountVnd, array $overrides = []): array
{
    $params = array_merge([
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_Amount' => $amountVnd * 100,
        'vnp_BankCode' => 'NCB',
        'vnp_CurrCode' => 'VND',
        'vnp_TxnRef' => $txnRef,
        'vnp_OrderInfo' => 'Thanh toan regression',
        'vnp_TransactionNo' => '15'.random_int(100000, 999999),
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_PayDate' => now()->format('YmdHis'),
    ], $overrides);
    $params['vnp_SecureHash'] = app(VnpayService::class)->signature($params);

    return $params;
}

function regressionHotelBooking(object $ctx, string $txnRef, float $amount = 1080000): Payment
{
    $booking = Booking::create([
        'uuid' => (string) Str::uuid(),
        'customer_id' => $ctx->customer->id,
        'hotel_id' => $ctx->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => now()->addDays(7)->toDateString(),
        'check_out' => now()->addDays(9)->toDateString(),
        'total_price' => $amount,
        'currency' => 'VND',
    ]);

    return Payment::create([
        'booking_id' => $booking->id,
        'amount' => $amount,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => $txnRef,
        'status' => PaymentStatus::PENDING->value,
    ]);
}

function regressionTourFixtures(object $ctx): TourBooking
{
    $country = Country::firstOrCreate(
        ['code' => 'VN'],
        ['name' => 'Vietnam', 'tax_rate' => 0.08, 'tax_name' => 'VAT'],
    );
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang', 'slug' => 'ha-giang-regression-'.Str::random(6),
        'description' => 'Test', 'is_featured' => false,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $ctx->vendor->id, 'business_name' => 'Regression Guide', 'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Regression tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);

    return app(CreateTourBookingAction::class)->handle(new CreateTourBookingData(
        customerId: $ctx->customer->id,
        tourId: $tour->id,
        slotId: $slot->id,
        pricingMode: 'hour',
        durationValue: 2,
    ));
}

test('hotel ipn keeps the documented response codes', function (): void {
    Event::fake([\App\Events\PaymentConfirmed::class]);

    $payment = regressionHotelBooking($this, 'regression-ipn-1');
    $booking = $payment->booking;
    $amount = (int) $payment->amount;

    $bad = regressionSignedIpn('regression-ipn-1', $amount);
    $bad['vnp_SecureHash'] = 'deadbeef';
    $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query($bad))
        ->assertOk()->assertJson(['RspCode' => '97']);

    $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query(
        regressionSignedIpn('regression-ipn-missing', $amount)
    ))->assertOk()->assertJson(['RspCode' => '01']);

    $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query(
        regressionSignedIpn('regression-ipn-1', $amount + 1)
    ))->assertOk()->assertJson(['RspCode' => '04']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PENDING->value)
        ->and($booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT->value);

    $query = http_build_query(regressionSignedIpn('regression-ipn-1', $amount));
    $this->getJson('/api/v1/payments/vnpay-ipn?'.$query)
        ->assertOk()->assertJson(['RspCode' => '00']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($booking->fresh()->status)->toBe(BookingStatus::CONFIRMED->value);

    $this->getJson('/api/v1/payments/vnpay-ipn?'.$query)
        ->assertOk()->assertJson(['RspCode' => '02']);
});

test('cancelling a coupon booking still releases quota while a refund is pending', function (): void {
    config([
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'regression-secret-1234567890',
    ]);
    Queue::fake();

    $coupon = Coupon::create([
        'code' => 'REGRESSIONCOUPON', 'type' => 'percentage', 'value' => 10,
        'is_active' => true, 'usage_limit_total' => 1, 'usage_limit_per_user' => 1,
    ]);

    $booking = app(CreateBookingAction::class)->handle(new CreateBookingData(
        customerId: $this->customer->id,
        hotelId: $this->hotel->id,
        roomQuantities: [$this->room->id => 1],
        checkIn: Carbon::today()->addDays(7)->toDateString(),
        checkOut: Carbon::today()->addDays(9)->toDateString(),
        couponCode: $coupon->code,
    ));

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'regression-coupon-txn-1',
        'status' => PaymentStatus::COMPLETED->value,
        'payload' => [
            'vnp_transaction_no' => '15713770',
            'vnp_create_date' => '20261024101010',
        ],
    ]);

    $coupons = app(CouponRepositoryInterface::class);
    expect($coupons->redemptionCount($coupon))->toBe(1);

    app(CancelBookingAction::class)->handle($booking->fresh()->load('payments'));

    expect($coupons->redemptionCount($coupon))->toBe(0)
        ->and(PaymentRefund::count())->toBe(1)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('refunded and disputed tour bookings still consume coupon quota', function (): void {
    $coupon = Coupon::create([
        'code' => 'REGRESSIONREFUNDED', 'type' => 'percentage', 'value' => 10,
        'is_active' => true,
    ]);
    $country = Country::firstOrCreate(
        ['code' => 'VN'],
        ['name' => 'Vietnam', 'tax_rate' => 0.08, 'tax_name' => 'VAT'],
    );
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Huế', 'slug' => 'hue-regression-'.Str::random(6),
        'description' => 'Test', 'is_featured' => false,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Regression Guide 2', 'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Regression tour 2', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);

    foreach ([TourBookingStatus::REFUNDED, TourBookingStatus::DISPUTED] as $index => $status) {
        $slot = TourAvailabilitySlot::create([
            'tour_id' => $tour->id, 'date' => Carbon::today()->addDays(7)->toDateString(),
            'start_time' => (8 + $index * 2).':00:00', 'end_time' => (10 + $index * 2).':00:00',
            'status' => 'available',
        ]);
        TourBooking::create([
            'customer_id' => $this->customer->id, 'tour_id' => $tour->id,
            'slot_id' => $slot->id, 'provider_id' => $provider->id,
            'province_id' => $province->id, 'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(7)->addHours(2), 'pricing_mode' => 'hour',
            'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
            'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
            'status' => $status->value, 'coupon_id' => $coupon->id,
        ]);
    }

    $coupons = app(CouponRepositoryInterface::class);

    expect($coupons->redemptionCount($coupon))->toBe(2);
});

test('payout reporting ignores pending refunds until the settlement slice adjusts net', function (): void {
    $booking = Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::COMPLETED->value,
        'check_in' => Carbon::today()->toDateString(),
        'check_out' => Carbon::today()->addDay()->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id, 'amount' => 1000000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'regression-payout-txn-1',
        'status' => PaymentStatus::COMPLETED->value,
    ]);
    PaymentRefund::create([
        'uuid' => (string) Str::uuid(),
        'payment_type' => PaymentRefund::TYPE_HOTEL,
        'payment_id' => $payment->id,
        'provider' => 'vnpay',
        'request_id' => (string) Str::uuid()->getHex(),
        'idempotency_key' => 'hotel-cancel:'.$booking->id.':'.$payment->id,
        'txn_ref' => 'regression-payout-txn-1',
        'amount' => 200000,
        'currency' => 'VND',
        'type' => '03',
        'status' => 'pending',
    ]);

    $payouts = app(PayoutService::class)->generateForPeriod(
        Carbon::today()->toDateString(),
        Carbon::today()->addDays(7)->toDateString(),
    );

    expect($payouts)->toHaveCount(1)
        ->and((float) $payouts[0]->amount)->toBe(1000000.0)
        ->and((float) $payouts[0]->net)->toBe(round(1000000.0 - (float) $payouts[0]->commission, 2));
});

test('hotel cancel api keeps its response shape while creating an obligation', function (): void {
    config([
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'regression-secret-1234567890',
    ]);
    Queue::fake();

    $booking = app(CreateBookingAction::class)->handle(new CreateBookingData(
        customerId: $this->customer->id,
        hotelId: $this->hotel->id,
        roomQuantities: [$this->room->id => 1],
        checkIn: Carbon::today()->addDays(7)->toDateString(),
        checkOut: Carbon::today()->addDays(9)->toDateString(),
    ));
    Payment::create([
        'booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'regression-cancel-shape-1',
        'status' => PaymentStatus::COMPLETED->value,
        'payload' => [
            'vnp_transaction_no' => '15713771',
            'vnp_create_date' => '20261024101010',
        ],
    ]);

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/bookings/'.$booking->uuid.'/cancel')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', BookingStatus::CANCELLED->value)
        ->assertJsonStructure(['success', 'data' => [
            'id', 'uuid', 'status', 'check_in', 'check_out', 'total_price', 'currency',
            'subtotal', 'discount_amount', 'tax_amount', 'is_guest',
        ]]);

    expect($response->json('data'))->not->toHaveKey('payments')
        ->and(PaymentRefund::count())->toBe(1);
});

test('tour cancel api keeps its response shape while creating an obligation', function (): void {
    config([
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'regression-secret-1234567890',
    ]);
    Queue::fake();

    $booking = regressionTourFixtures($this);
    $booking->update(['status' => TourBookingStatus::CONFIRMED->value]);
    TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'regression-cancel-shape-tour-1',
        'status' => PaymentStatus::COMPLETED->value,
        'payload' => [
            'vnp_transaction_no' => '15713772',
            'vnp_create_date' => '20261024101010',
        ],
    ]);

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$booking->uuid.'/cancel')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', TourBookingStatus::CANCELLED->value)
        ->assertJsonStructure(['success', 'data' => [
            'id', 'uuid', 'status', 'start_at', 'end_at', 'pricing_mode',
            'duration_value', 'total_price', 'currency', 'can_pay', 'can_open_dispute',
        ]]);

    expect($response->json('data'))->not->toHaveKey('payments');
});

test('flag off freezes local refund accounting for hotel and tour cancels', function (): void {
    config(['services.vnpay.refund_enabled' => false]);
    Queue::fake();

    $hotelBooking = app(CreateBookingAction::class)->handle(new CreateBookingData(
        customerId: $this->customer->id,
        hotelId: $this->hotel->id,
        roomQuantities: [$this->room->id => 1],
        checkIn: Carbon::today()->addDays(7)->toDateString(),
        checkOut: Carbon::today()->addDays(9)->toDateString(),
    ));
    $hotelPayment = Payment::create([
        'booking_id' => $hotelBooking->id,
        'amount' => (float) $hotelBooking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'regression-flag-off-hotel-1',
        'status' => PaymentStatus::COMPLETED->value,
    ]);

    app(CancelBookingAction::class)->handle($hotelBooking->fresh()->load('payments'));

    expect(PaymentRefund::count())->toBe(0)
        ->and((float) $hotelPayment->fresh()->refunded_amount)->toBe((float) $hotelPayment->amount)
        ->and($hotelPayment->fresh()->status)->toBe(PaymentStatus::REFUNDED->value);

    $tourBooking = regressionTourFixtures($this);
    $tourPayment = TourPayment::create([
        'tour_booking_id' => $tourBooking->id,
        'amount' => (float) $tourBooking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'regression-flag-off-tour-1',
        'status' => PaymentStatus::COMPLETED->value,
    ]);

    app(CancelTourBookingAction::class)->handle($tourBooking->fresh()->load('payments'));

    expect(PaymentRefund::count())->toBe(0)
        ->and((float) $tourPayment->fresh()->refunded_amount)->toBe((float) $tourPayment->amount)
        ->and($tourPayment->fresh()->status)->toBe(PaymentStatus::REFUNDED->value);
});

test('the refund flag defaults to disabled', function (): void {
    expect(config('services.vnpay.refund_enabled'))->toBeFalse();
});
