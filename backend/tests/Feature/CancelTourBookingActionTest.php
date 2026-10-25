<?php

use App\Actions\CancelTourBookingAction;
use App\Actions\CreateTourBookingAction;
use App\DTOs\CreateTourBookingData;
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
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Cancel Vendor', 'email' => 'tour-cancel-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Cancel Customer', 'email' => 'tour-cancel-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang', 'slug' => 'ha-giang-cancel',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Test Guide', 'status' => 'approved',
    ]);
    $this->provider = $provider;
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

function makeTourBooking(object $ctx): TourBooking
{
    return app(CreateTourBookingAction::class)->handle(new CreateTourBookingData(
        customerId: $ctx->customer->id,
        tourId: $ctx->tour->id,
        slotId: $ctx->slot->id,
        pricingMode: 'hour',
        durationValue: 2,
    ));
}

test('action cancels booking and releases slot', function (): void {
    $booking = makeTourBooking($this);

    $result = app(CancelTourBookingAction::class)->handle($booking->load('payments'));

    expect($result->status)->toBe(TourBookingStatus::CANCELLED->value)
        ->and(TourAvailabilitySlot::find($this->slot->id)->status)->toBe('available');
});

test('action is idempotent when slot already released', function (): void {
    $booking = makeTourBooking($this);

    app(CancelTourBookingAction::class)->handle($booking->load('payments'));
    $second = app(CancelTourBookingAction::class)->handle($booking->fresh()->load('payments'));

    expect($second->status)->toBe(TourBookingStatus::CANCELLED->value)
        ->and(TourAvailabilitySlot::find($this->slot->id)->status)->toBe('available');
});

test('action refunds completed payment on cancel', function (): void {
    $booking = makeTourBooking($this);
    $payment = TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'tour-txn-'.uniqid(),
        'status' => PaymentStatus::COMPLETED->value,
    ]);

    $result = app(CancelTourBookingAction::class)->handle($booking->fresh()->load('payments'));

    expect($result->status)->toBe(TourBookingStatus::CANCELLED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe((float) $payment->amount)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::REFUNDED->value);
});

test('action does not refund failed payment on cancel', function (): void {
    $booking = makeTourBooking($this);
    $payment = TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'tour-txn-'.uniqid(),
        'status' => PaymentStatus::FAILED->value,
    ]);

    $result = app(CancelTourBookingAction::class)->handle($booking->fresh()->load('payments'));

    expect($result->status)->toBe(TourBookingStatus::CANCELLED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::FAILED->value);
});

test('action creates a tour refund obligation when refunds are enabled', function (): void {
    config([
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'tour-cancel-secret',
    ]);
    Queue::fake();

    $booking = makeTourBooking($this);
    $payment = TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'tour-txn-'.uniqid(),
        'status' => PaymentStatus::COMPLETED->value,
        'payload' => [
            'vnp_transaction_no' => '14000001',
            'vnp_create_date' => '20261024101010',
        ],
    ]);

    app(CancelTourBookingAction::class)->handle($booking->fresh()->load('payments'));

    $refund = \App\Models\PaymentRefund::first();

    expect($refund)->not->toBeNull()
        ->and($refund->payment_type)->toBe(\App\Models\PaymentRefund::TYPE_TOUR)
        ->and($refund->status)->toBe('pending')
        ->and($refund->type)->toBe('02')
        ->and((float) $refund->amount)->toBe((float) $booking->total_price)
        ->and($refund->idempotency_key)->toBe('tour-cancel:'.$booking->id.':'.$payment->id)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('double cancel keeps a single tour obligation', function (): void {
    config([
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'tour-cancel-secret',
    ]);
    Queue::fake();

    $booking = makeTourBooking($this);
    $payment = TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'tour-txn-'.uniqid(),
        'status' => PaymentStatus::COMPLETED->value,
        'payload' => [
            'vnp_transaction_no' => '14000002',
            'vnp_create_date' => '20261024101010',
        ],
    ]);

    app(CancelTourBookingAction::class)->handle($booking->fresh()->load('payments'));
    app(CancelTourBookingAction::class)->handle($booking->fresh()->load('payments'));

    expect(\App\Models\PaymentRefund::count())->toBe(1)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('guide-created tour bookings use the same refund obligation path', function (): void {
    config([
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'tour-cancel-secret',
    ]);
    Queue::fake();

    $booking = app(\App\Actions\CreateGuideTourBookingAction::class)->handle(
        new \App\DTOs\CreateGuideTourBookingData(
            customerId: $this->customer->id,
            providerUuid: $this->provider->uuid,
            slotId: $this->slot->id,
            travelDate: Carbon::tomorrow()->toDateString(),
        ),
    );
    $booking->update(['status' => TourBookingStatus::CONFIRMED->value]);

    $payment = TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'tour-guide-txn-'.uniqid(),
        'status' => PaymentStatus::COMPLETED->value,
        'payload' => [
            'vnp_transaction_no' => '14000003',
            'vnp_create_date' => '20261024101010',
        ],
    ]);

    app(CancelTourBookingAction::class)->handle($booking->fresh()->load('payments'));

    $refund = \App\Models\PaymentRefund::first();

    expect($refund)->not->toBeNull()
        ->and($refund->payment_type)->toBe(\App\Models\PaymentRefund::TYPE_TOUR)
        ->and((float) $refund->amount)->toBe((float) $booking->total_price)
        ->and($refund->idempotency_key)->toBe('tour-cancel:'.$booking->id.':'.$payment->id)
        ->and($booking->fresh()->status)->toBe(TourBookingStatus::CANCELLED->value);
});
