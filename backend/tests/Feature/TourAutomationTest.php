<?php

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourDispute;
use App\Models\TourPayment;
use App\Models\TourProduct;
use App\Models\TourProvince;
use App\Models\TourProvider;
use App\Models\User;
use App\Services\TourCommissionService;
use App\Services\TourPaymentService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Auto Vendor', 'email' => 'auto-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Auto Customer', 'email' => 'auto-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Cần Thơ', 'slug' => 'can-tho-auto',
        'description' => 'Test', 'is_featured' => false,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Auto Guide',
        'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Auto tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
});

test('tour:expire-holds releases only expired holds', function (): void {
    $expired = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00',
        'status' => 'held', 'held_until' => now()->subMinutes(5),
    ]);
    $fresh = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '10:00:00', 'end_time' => '12:00:00',
        'status' => 'held', 'held_until' => now()->addMinutes(10),
    ]);

    $this->artisan('tour:expire-holds')->assertSuccessful();

    expect($expired->fresh()->status)->toBe('available')
        ->and($expired->fresh()->held_until)->toBeNull()
        ->and($expired->fresh()->tour_booking_id)->toBeNull()
        ->and($fresh->fresh()->status)->toBe('held');
});

test('tour:auto-complete advances bookings by time', function (): void {
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::yesterday()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    $past = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->tour->provider_id,
        'province_id' => $this->tour->province_id,
        'start_at' => now()->subHours(3), 'end_at' => now()->subHour(),
        'pricing_mode' => 'hour', 'duration_value' => 2,
        'base_fixed' => 200000, 'unit_price' => 100000, 'subtotal' => 400000,
        'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);
    $runningSlot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    $running = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $runningSlot->id, 'provider_id' => $this->tour->provider_id,
        'province_id' => $this->tour->province_id,
        'start_at' => now()->subHour(), 'end_at' => now()->addHour(),
        'pricing_mode' => 'hour', 'duration_value' => 2,
        'base_fixed' => 200000, 'unit_price' => 100000, 'subtotal' => 400000,
        'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);
    $futureSlot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    $future = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $futureSlot->id, 'provider_id' => $this->tour->provider_id,
        'province_id' => $this->tour->province_id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHours(2),
        'pricing_mode' => 'hour', 'duration_value' => 2,
        'base_fixed' => 200000, 'unit_price' => 100000, 'subtotal' => 400000,
        'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);

    // One run: overdue confirmed jumps to completed, started-but-unfinished lands on ongoing, future untouched.
    $this->artisan('tour:auto-complete')->assertSuccessful();
    expect($past->fresh()->status)->toBe(TourBookingStatus::COMPLETED->value)
        ->and($running->fresh()->status)->toBe(TourBookingStatus::ONGOING->value)
        ->and($future->fresh()->status)->toBe(TourBookingStatus::CONFIRMED->value);
});

test('tour refundFull settles local accounting', function (): void {
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    $booking = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->tour->provider_id,
        'province_id' => $this->tour->province_id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHours(2),
        'pricing_mode' => 'hour', 'duration_value' => 2,
        'base_fixed' => 200000, 'unit_price' => 100000, 'subtotal' => 400000,
        'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);
    $payment = TourPayment::create([
        'tour_booking_id' => $booking->id, 'amount' => 500000,
        'currency' => 'VND', 'provider' => 'vnpay', 'status' => PaymentStatus::COMPLETED->value,
    ]);

    app(TourPaymentService::class)->refund($payment->fresh(), 200000, 'partial');
    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(200000.0);

    app(TourPaymentService::class)->refundFull($payment->fresh(), 'rest');
    expect($payment->fresh()->status)->toBe(PaymentStatus::REFUNDED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(500000.0);
});

test('tour commission report aggregates by vendor', function (): void {
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->tour->provider_id,
        'province_id' => $this->tour->province_id,
        'start_at' => now(), 'end_at' => now()->addHours(2),
        'pricing_mode' => 'hour', 'duration_value' => 2,
        'base_fixed' => 200000, 'unit_price' => 100000, 'subtotal' => 400000,
        'total_price' => 1000000, 'currency' => 'VND',
        'status' => TourBookingStatus::COMPLETED->value,
    ]);

    $report = app(TourCommissionService::class)->reportByVendor();
    expect($report)->toHaveCount(1)
        ->and((int) $report[0]['vendor_id'])->toBe((int) $this->vendor->id)
        ->and((float) $report[0]['gross'])->toBe(1000000.0);

    $totals = app(TourCommissionService::class)->platformTotals();
    expect($totals['booking_count'])->toBe(1)
        ->and((float) $totals['revenue'])->toBe(1000000.0);
});

test('resolving tour dispute with refund settles payments and booking', function (): void {
    foreach (['super-admin'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    $admin = User::create([
        'name' => 'Dispute Admin', 'email' => 'dispute-admin@test.local',
        'password' => bcrypt('password'), 'role' => 'super_admin', 'status' => 'active',
    ]);
    $admin->assignRole('super-admin');

    $slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::today()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'available',
    ]);
    $booking = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $slot->id, 'provider_id' => $this->tour->provider_id,
        'province_id' => $this->tour->province_id,
        'start_at' => now(), 'end_at' => now()->addHours(2),
        'pricing_mode' => 'hour', 'duration_value' => 2,
        'base_fixed' => 200000, 'unit_price' => 100000, 'subtotal' => 400000,
        'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);
    $payment = TourPayment::create([
        'tour_booking_id' => $booking->id, 'amount' => 500000,
        'currency' => 'VND', 'provider' => 'vnpay', 'status' => PaymentStatus::COMPLETED->value,
    ]);
    $dispute = TourDispute::create([
        'tour_booking_id' => $booking->id, 'status' => 'open',
        'customer_notes' => 'The guide never showed up at the meeting point.',
    ]);

    $this->actingAs($admin)->patch('/admin/tour-disputes/'.$dispute->id, [
        'status' => 'resolved', 'refund_payments' => '1',
    ])->assertRedirect('/admin/tour-disputes/'.$dispute->id);

    expect($payment->fresh()->status)->toBe(PaymentStatus::REFUNDED->value)
        ->and($booking->fresh()->status)->toBe(TourBookingStatus::REFUNDED->value);
});
