<?php

use App\Enums\BookingStatus;
use App\Enums\TourBookingStatus;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Carbon\Carbon;

beforeEach(function (): void {
    $vendor = User::create([
        'name' => 'Coupon Repo Vendor', 'email' => 'coupon-repo-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Coupon Repo Customer', 'email' => 'coupon-repo-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->otherCustomer = User::create([
        'name' => 'Coupon Repo Other', 'email' => 'coupon-repo-other@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Coupon Repo Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam', 'status' => 'active',
    ]);
    $this->coupon = Coupon::create([
        'code' => 'REPOCOUPON', 'type' => 'percentage', 'value' => 10, 'is_active' => true,
    ]);

    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Repo Province', 'slug' => 'repo-province',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $vendor->id, 'business_name' => 'Repo Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Repo tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $this->provider = $provider;
    $this->province = $province;
});

test('hotel only', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => $this->coupon->id,
    ]);
    expect($coupons->redemptionCount($this->coupon))->toBe(1);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(1);
});

test('tour only', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->addDays(7)->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'booked',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->province->id, 'start_at' => Carbon::today()->addDays(7)->toDateString().' 08:00:00',
        'end_at' => Carbon::today()->addDays(7)->toDateString().' 10:00:00', 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::PENDING_PAYMENT->value, 'coupon_id' => $this->coupon->id,
    ]);
    expect($coupons->redemptionCount($this->coupon))->toBe(1);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(1);
});

test('combined', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::CONFIRMED->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => $this->coupon->id,
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->addDays(7)->toDateString(),
        'start_time' => '09:00:00', 'end_time' => '11:00:00', 'status' => 'booked',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->province->id, 'start_at' => Carbon::today()->addDays(7)->toDateString().' 09:00:00',
        'end_at' => Carbon::today()->addDays(7)->toDateString().' 11:00:00', 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::PENDING_PAYMENT->value, 'coupon_id' => $this->coupon->id,
    ]);
    expect($coupons->redemptionCount($this->coupon))->toBe(2);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(2);
});

test('per user', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => $this->coupon->id,
    ]);
    Booking::create([
        'customer_id' => $this->otherCustomer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => $this->coupon->id,
    ]);
    expect($coupons->redemptionCount($this->coupon))->toBe(2);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(1);
    expect($coupons->redemptionCountForUser($this->coupon, $this->otherCustomer->id))->toBe(1);
});

test('guest', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    Booking::create([
        'customer_id' => null, 'guest_email' => 'guest@test.local', 'guest_name' => 'Guest',
        'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => $this->coupon->id,
    ]);
    expect($coupons->redemptionCount($this->coupon))->toBe(1);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(0);
});

test('cancelled', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::CANCELLED->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => $this->coupon->id,
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->addDays(7)->toDateString(),
        'start_time' => '10:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->province->id, 'start_at' => Carbon::today()->addDays(7)->toDateString().' 10:00:00',
        'end_at' => Carbon::today()->addDays(7)->toDateString().' 12:00:00', 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CANCELLED->value, 'coupon_id' => $this->coupon->id,
    ]);
    expect($coupons->redemptionCount($this->coupon))->toBe(0);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(0);
});

test('soft deleted', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    $booking = Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => $this->coupon->id,
    ]);
    $booking->delete();
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->addDays(7)->toDateString(),
        'start_time' => '11:00:00', 'end_time' => '13:00:00', 'status' => 'available',
    ]);
    $tourBooking = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->province->id, 'start_at' => Carbon::today()->addDays(7)->toDateString().' 11:00:00',
        'end_at' => Carbon::today()->addDays(7)->toDateString().' 13:00:00', 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::PENDING_PAYMENT->value, 'coupon_id' => $this->coupon->id,
    ]);
    $tourBooking->delete();
    expect($coupons->redemptionCount($this->coupon))->toBe(0);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(0);
});

test('refunded disputed', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    $slot1 = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->addDays(7)->toDateString(),
        'start_time' => '12:00:00', 'end_time' => '14:00:00', 'status' => 'available',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot1->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->province->id, 'start_at' => Carbon::today()->addDays(7)->toDateString().' 12:00:00',
        'end_at' => Carbon::today()->addDays(7)->toDateString().' 14:00:00', 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::REFUNDED->value, 'coupon_id' => $this->coupon->id,
    ]);
    $slot2 = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->addDays(7)->toDateString(),
        'start_time' => '14:00:00', 'end_time' => '16:00:00', 'status' => 'available',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot2->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->province->id, 'start_at' => Carbon::today()->addDays(7)->toDateString().' 14:00:00',
        'end_at' => Carbon::today()->addDays(7)->toDateString().' 16:00:00', 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::DISPUTED->value, 'coupon_id' => $this->coupon->id,
    ]);
    expect($coupons->redemptionCount($this->coupon))->toBe(2);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(2);
});

test('null coupon', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    $nullCoupon = Coupon::create([
        'code' => 'NOBOOK', 'type' => 'fixed', 'value' => 5, 'is_active' => true,
    ]);
    expect($coupons->redemptionCount($nullCoupon))->toBe(0);
    expect($coupons->redemptionCountForUser($nullCoupon, $this->customer->id))->toBe(0);
    expect($coupons->findActiveByCode('does-not-exist'))->toBeNull();
});

test('bookings without a coupon are not counted', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => null,
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->addDays(7)->toDateString(),
        'start_time' => '16:00:00', 'end_time' => '18:00:00', 'status' => 'available',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->province->id, 'start_at' => Carbon::today()->addDays(7)->toDateString().' 16:00:00',
        'end_at' => Carbon::today()->addDays(7)->toDateString().' 18:00:00', 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::PENDING_PAYMENT->value, 'coupon_id' => null,
    ]);

    expect($coupons->redemptionCount($this->coupon))->toBe(0);
    expect($coupons->redemptionCountForUser($this->coupon, $this->customer->id))->toBe(0);
});

test('for update', function (): void {
    $coupons = app(CouponRepositoryInterface::class);
    $found = $coupons->findActiveByCodeForUpdate('repocoupon');
    expect($found?->id)->toBe($this->coupon->id);
    $missing = $coupons->findActiveByCodeForUpdate('nope');
    expect($missing)->toBeNull();
});
