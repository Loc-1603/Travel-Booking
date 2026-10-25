<?php

use App\Actions\CreateBookingAction;
use App\Actions\CreateTourBookingAction;
use App\DTOs\CreateBookingData;
use App\DTOs\CreateTourBookingData;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Shared Coupon Vendor', 'email' => 'shared-coupon-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Shared Coupon Customer', 'email' => 'shared-coupon-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Shared Coupon Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam', 'status' => 'active',
        'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Shared Coupon Room',
        'capacity' => 2, 'base_price' => 500000, 'total_rooms' => 5,
    ]);

    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Shared Coupon Province', 'slug' => 'shared-coupon-province',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Shared Coupon Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Shared Coupon Tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
    $this->slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);

    $this->coupon = Coupon::create([
        'code' => 'SHAREDCOUPON', 'type' => 'percentage', 'value' => 10,
        'is_active' => true, 'usage_limit_total' => 1, 'usage_limit_per_user' => 1,
    ]);
});

function sharedCouponCode(): string
{
    return 'SHAREDCOUPON';
}

function makeHotelBookingWithCoupon(object $ctx, ?string $couponCode = null): Booking
{
    return app(CreateBookingAction::class)->handle(new CreateBookingData(
        customerId: $ctx->customer->id,
        hotelId: $ctx->hotel->id,
        roomQuantities: [$ctx->room->id => 1],
        checkIn: Carbon::today()->addDays(7)->toDateString(),
        checkOut: Carbon::today()->addDays(9)->toDateString(),
        couponCode: $couponCode ?? sharedCouponCode(),
    ));
}

function makeTourBookingWithCoupon(object $ctx, ?TourAvailabilitySlot $slot = null, ?string $couponCode = null): TourBooking
{
    $slot = $slot ?? $ctx->slot;

    return app(CreateTourBookingAction::class)->handle(new CreateTourBookingData(
        customerId: $ctx->customer->id,
        tourId: $ctx->tour->id,
        slotId: $slot->id,
        pricingMode: 'hour',
        durationValue: 4,
        couponCode: $couponCode ?? sharedCouponCode(),
    ));
}

test('C-1 hotel store applies coupon when quota is available', function (): void {
    $booking = makeHotelBookingWithCoupon($this);

    expect($booking->coupon_id)->toBe($this->coupon->id)
        ->and((float) $booking->discount_amount)->toBeGreaterThan(0);
});

test('C-1 hotel store does not apply coupon when quota is exhausted by a prior hotel booking', function (): void {
    makeHotelBookingWithCoupon($this);

    $second = makeHotelBookingWithCoupon($this);

    expect($second->coupon_id)->toBeNull()
        ->and((float) $second->discount_amount)->toBe(0.0);
});

test('C-1 hotel store does not apply coupon when quota is exhausted by a prior tour booking', function (): void {
    makeTourBookingWithCoupon($this);

    $hotelBooking = makeHotelBookingWithCoupon($this);

    expect($hotelBooking->coupon_id)->toBeNull()
        ->and((float) $hotelBooking->discount_amount)->toBe(0.0);
});

test('C-2 tour store applies coupon when quota is available', function (): void {
    $booking = makeTourBookingWithCoupon($this);

    expect($booking->coupon_id)->toBe($this->coupon->id)
        ->and((float) $booking->discount_amount)->toBeGreaterThan(0);
});

test('C-2 tour store does not apply coupon when quota is exhausted by a prior tour booking', function (): void {
    makeTourBookingWithCoupon($this);

    $secondSlot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id,
        'date' => Carbon::tomorrow()->addDay()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'status' => 'available',
    ]);

    $second = makeTourBookingWithCoupon($this, $secondSlot);

    expect($second->coupon_id)->toBeNull()
        ->and((float) $second->discount_amount)->toBe(0.0);
});

test('C-2 tour store does not apply coupon when quota is exhausted by a prior hotel booking', function (): void {
    makeHotelBookingWithCoupon($this);

    $tourBooking = makeTourBookingWithCoupon($this);

    expect($tourBooking->coupon_id)->toBeNull()
        ->and((float) $tourBooking->discount_amount)->toBe(0.0);
});

test('C-3 per-user limit is shared across hotel and tour bookings', function (): void {
    $coupon = \App\Models\Coupon::create([
        'code' => 'PERUSERCOUPON', 'type' => 'percentage', 'value' => 10,
        'is_active' => true, 'usage_limit_total' => 10, 'usage_limit_per_user' => 1,
    ]);

    $hotelBooking = app(\App\Actions\CreateBookingAction::class)->handle(new \App\DTOs\CreateBookingData(
        customerId: $this->customer->id,
        hotelId: $this->hotel->id,
        roomQuantities: [$this->room->id => 1],
        checkIn: \Carbon\Carbon::today()->addDays(7)->toDateString(),
        checkOut: \Carbon\Carbon::today()->addDays(9)->toDateString(),
        couponCode: 'PERUSERCOUPON',
    ));
    expect($hotelBooking->coupon_id)->toBe($coupon->id);

    $tourBooking = app(\App\Actions\CreateTourBookingAction::class)->handle(new \App\DTOs\CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $this->slot->id,
        pricingMode: 'hour',
        durationValue: 4,
        couponCode: 'PERUSERCOUPON',
    ));
    expect($tourBooking->coupon_id)->toBeNull();
});
test('C-3 guest hotel booking is not subject to per-user limit', function (): void {

    $coupon = \App\Models\Coupon::create([
        'code' => 'GUESTCOUPON', 'type' => 'percentage', 'value' => 10,
        'is_active' => true, 'usage_limit_total' => 2, 'usage_limit_per_user' => 1,
    ]);

    $customerBooking = app(\App\Actions\CreateBookingAction::class)->handle(new \App\DTOs\CreateBookingData(
        customerId: $this->customer->id,
        hotelId: $this->hotel->id,
        roomQuantities: [$this->room->id => 1],
        checkIn: \Carbon\Carbon::today()->addDays(10)->toDateString(),
        checkOut: \Carbon\Carbon::today()->addDays(12)->toDateString(),
        couponCode: 'GUESTCOUPON',
    ));
    expect($customerBooking->coupon_id)->toBe($coupon->id);

    $guestBooking = app(\App\Actions\CreateBookingAction::class)->handle(new \App\DTOs\CreateBookingData(
        customerId: null,
        hotelId: $this->hotel->id,
        roomQuantities: [$this->room->id => 1],
        checkIn: \Carbon\Carbon::today()->addDays(13)->toDateString(),
        checkOut: \Carbon\Carbon::today()->addDays(15)->toDateString(),
        guestEmail: 'guest-coupon@test.local',
        guestName: 'Guest Coupon',
        couponCode: 'GUESTCOUPON',
    ));
    expect($guestBooking->coupon_id)->toBe($coupon->id);
});

test('C-4 cancel hotel booking returns coupon quota', function (): void {
    $coupon = \App\Models\Coupon::create([
        'code' => 'CANCELHOTELCOUPON', 'type' => 'percentage', 'value' => 10,
        'is_active' => true, 'usage_limit_total' => 1, 'usage_limit_per_user' => 1,
    ]);

    $booking = app(\App\Actions\CreateBookingAction::class)->handle(new \App\DTOs\CreateBookingData(
        customerId: $this->customer->id,
        hotelId: $this->hotel->id,
        roomQuantities: [$this->room->id => 1],
        checkIn: \Carbon\Carbon::today()->addDays(20)->toDateString(),
        checkOut: \Carbon\Carbon::today()->addDays(22)->toDateString(),
        couponCode: 'CANCELHOTELCOUPON',
    ));
    expect($booking->coupon_id)->toBe($coupon->id);

    app(\App\Actions\CancelBookingAction::class)->handle($booking);

    $second = app(\App\Actions\CreateBookingAction::class)->handle(new \App\DTOs\CreateBookingData(
        customerId: $this->customer->id,
        hotelId: $this->hotel->id,
        roomQuantities: [$this->room->id => 1],
        checkIn: \Carbon\Carbon::today()->addDays(23)->toDateString(),
        checkOut: \Carbon\Carbon::today()->addDays(25)->toDateString(),
        couponCode: 'CANCELHOTELCOUPON',
    ));
    expect($second->coupon_id)->toBe($coupon->id);
});

test('C-4 cancel tour booking returns coupon quota', function (): void {
    $coupon = \App\Models\Coupon::create([
        'code' => 'CANCELOURCOUPON', 'type' => 'percentage', 'value' => 10,
        'is_active' => true, 'usage_limit_total' => 1, 'usage_limit_per_user' => 1,
    ]);

    $booking = app(\App\Actions\CreateTourBookingAction::class)->handle(new \App\DTOs\CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $this->slot->id,
        pricingMode: 'hour',
        durationValue: 4,
        couponCode: 'CANCELOURCOUPON',
    ));
    expect($booking->coupon_id)->toBe($coupon->id);

    app(\App\Actions\CancelTourBookingAction::class)->handle($booking);

    $secondSlot = \App\Models\TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id,
        'date' => \Carbon\Carbon::tomorrow()->addDay()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'status' => 'available',
    ]);

    $second = app(\App\Actions\CreateTourBookingAction::class)->handle(new \App\DTOs\CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $secondSlot->id,
        pricingMode: 'hour',
        durationValue: 4,
        couponCode: 'CANCELOURCOUPON',
    ));
    expect($second->coupon_id)->toBe($coupon->id);
});

test('C-4 expire pending tour booking returns coupon quota', function (): void {
    $coupon = \App\Models\Coupon::create([
        'code' => 'EXPIRETOURCOUPON', 'type' => 'percentage', 'value' => 10,
        'is_active' => true, 'usage_limit_total' => 1, 'usage_limit_per_user' => 1,
    ]);

    $booking = app(\App\Actions\CreateTourBookingAction::class)->handle(new \App\DTOs\CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $this->slot->id,
        pricingMode: 'hour',
        durationValue: 4,
        couponCode: 'EXPIRETOURCOUPON',
    ));
    expect($booking->coupon_id)->toBe($coupon->id);

    \Illuminate\Support\Facades\DB::table('tour_bookings')
        ->where('id', $booking->id)
        ->update(['created_at' => now()->subHours(2)]);

    $expiredCount = app(\App\Services\TourBookingService::class)->expirePendingBookings();
    expect($expiredCount)->toBeGreaterThanOrEqual(1)
        ->and($booking->fresh()->status)->toBe(\App\Enums\TourBookingStatus::CANCELLED->value);

    $newSlot = \App\Models\TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id,
        'date' => \Carbon\Carbon::tomorrow()->addDays(2)->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'status' => 'available',
    ]);

    $second = app(\App\Actions\CreateTourBookingAction::class)->handle(new \App\DTOs\CreateTourBookingData(
        customerId: $this->customer->id,
        tourId: $this->tour->id,
        slotId: $newSlot->id,
        pricingMode: 'hour',
        durationValue: 4,
        couponCode: 'EXPIRETOURCOUPON',
    ));
    expect($second->coupon_id)->toBe($coupon->id);
});

test('C-5 hotel ipn keeps coupon id amount and confirms booking', function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $booking = makeHotelBookingWithCoupon($this);
    $couponId = $booking->coupon_id;
    $totalPrice = (float) $booking->total_price;

    $payment = \App\Models\Payment::create([
        'booking_id' => $booking->id,
        'amount' => $totalPrice,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'hotel-coupon-ipn-1',
        'status' => \App\Enums\PaymentStatus::PENDING->value,
    ]);

    $params = [
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_Amount' => (int) ($totalPrice * 100),
        'vnp_BankCode' => 'NCB',
        'vnp_CurrCode' => 'VND',
        'vnp_TxnRef' => 'hotel-coupon-ipn-1',
        'vnp_OrderInfo' => 'Hotel coupon ipn',
        'vnp_TransactionNo' => '14123456',
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_PayDate' => now()->format('YmdHis'),
    ];
    $params['vnp_SecureHash'] = app(\App\Services\VnpayService::class)->signature($params);

    $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query($params))
        ->assertOk()
        ->assertJson(['RspCode' => '00']);

    expect($payment->fresh()->status)->toBe(\App\Enums\PaymentStatus::COMPLETED->value)
        ->and($booking->fresh()->status)->toBe(\App\Enums\BookingStatus::CONFIRMED->value)
        ->and($booking->fresh()->coupon_id)->toBe($couponId)
        ->and((float) $booking->fresh()->total_price)->toBe($totalPrice);
});

test('C-5 tour ipn keeps coupon id amount and confirms booking', function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $booking = makeTourBookingWithCoupon($this);
    $couponId = $booking->coupon_id;
    $totalPrice = (float) $booking->total_price;

    $payment = \App\Models\TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => $totalPrice,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'tour-coupon-ipn-1',
        'status' => \App\Enums\PaymentStatus::PENDING->value,
    ]);

    $params = [
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_Amount' => (int) ($totalPrice * 100),
        'vnp_BankCode' => 'NCB',
        'vnp_CurrCode' => 'VND',
        'vnp_TxnRef' => 'tour-coupon-ipn-1',
        'vnp_OrderInfo' => 'Tour coupon ipn',
        'vnp_TransactionNo' => '14123457',
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_PayDate' => now()->format('YmdHis'),
    ];
    $params['vnp_SecureHash'] = app(\App\Services\VnpayService::class)->signature($params);

    $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query($params))
        ->assertOk()
        ->assertJson(['RspCode' => '00']);

    expect($payment->fresh()->status)->toBe(\App\Enums\PaymentStatus::COMPLETED->value)
        ->and($booking->fresh()->status)->toBe(\App\Enums\TourBookingStatus::CONFIRMED->value)
        ->and($booking->fresh()->coupon_id)->toBe($couponId)
        ->and((float) $booking->fresh()->total_price)->toBe($totalPrice);
});
