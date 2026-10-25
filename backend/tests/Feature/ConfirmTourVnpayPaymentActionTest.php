<?php

use App\Actions\ConfirmTourVnpayPaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\OutboxEvent;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourPayment;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\VnpayService;
use Carbon\Carbon;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $customer = User::create([
        'name' => 'Confirm Customer', 'email' => 'tour-confirm-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $vendor = User::create([
        'name' => 'Confirm Vendor', 'email' => 'tour-confirm-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Ha Giang', 'slug' => 'ha-giang-confirm',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $vendor->id, 'business_name' => 'Confirm Guide', 'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Confirm tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'booked',
    ]);
    $this->booking = TourBooking::create([
        'customer_id' => $customer->id, 'tour_id' => $tour->id,
        'slot_id' => $slot->id, 'provider_id' => $provider->id,
        'province_id' => $province->id, 'start_at' => Carbon::tomorrow()->setTime(8, 0),
        'end_at' => Carbon::tomorrow()->setTime(10, 0), 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::PENDING_PAYMENT->value,
    ]);
    $this->payment = TourPayment::create([
        'tour_booking_id' => $this->booking->id, 'amount' => 500000,
        'currency' => 'VND', 'provider' => 'vnpay', 'external_id' => 'tour_confirm-1',
        'status' => PaymentStatus::PENDING->value,
    ]);
    $this->event = WebhookEvent::create([
        'provider' => 'vnpay', 'event_id' => 'tour:vnpay:tour_confirm-1:14000001',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function makeTourIpnPayload(array $overrides = []): array
{
    $params = array_merge([
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_Amount' => 500000 * 100,
        'vnp_BankCode' => 'NCB',
        'vnp_CurrCode' => 'VND',
        'vnp_TxnRef' => 'tour_confirm-1',
        'vnp_OrderInfo' => 'Thanh toan tour',
        'vnp_TransactionNo' => '14000001',
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_PayDate' => now()->format('YmdHis'),
    ], $overrides);
    $params['vnp_SecureHash'] = app(VnpayService::class)->signature($params);

    return $params;
}

test('action confirms a successful tour ipn', function (): void {
    $handled = app(ConfirmTourVnpayPaymentAction::class)->handle(
        $this->event->event_id,
        $this->payment->id,
        makeTourIpnPayload(),
    );

    expect($handled)->toBeTrue()
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($this->booking->fresh()->status)->toBe(TourBookingStatus::CONFIRMED->value)
        ->and($this->event->fresh()->processed_at)->not->toBeNull()
        ->and($this->payment->fresh()->payload['vnp_transaction_no'])->toBe('14000001')
        ->and(OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$this->payment->id)->exists())->toBeTrue();
});

test('action marks payment failed for non-success response code', function (): void {
    $handled = app(ConfirmTourVnpayPaymentAction::class)->handle(
        $this->event->event_id,
        $this->payment->id,
        makeTourIpnPayload(['vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02']),
    );

    expect($handled)->toBeTrue()
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::FAILED->value)
        ->and($this->booking->fresh()->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value)
        ->and($this->event->fresh()->processed_at)->not->toBeNull();
});

test('action skips an already processed webhook event', function (): void {
    $this->event->update(['processed_at' => now()]);

    $handled = app(ConfirmTourVnpayPaymentAction::class)->handle(
        $this->event->event_id,
        $this->payment->id,
        makeTourIpnPayload(),
    );

    expect($handled)->toBeFalse()
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::PENDING->value)
        ->and($this->booking->fresh()->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value);
});

test('action rejects an invalid signature and stamps the event', function (): void {
    $payload = makeTourIpnPayload();
    $payload['vnp_SecureHash'] = 'deadbeef';

    $handled = app(ConfirmTourVnpayPaymentAction::class)->handle(
        $this->event->event_id,
        $this->payment->id,
        $payload,
    );

    expect($handled)->toBeFalse()
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::PENDING->value)
        ->and($this->event->fresh()->processed_at)->not->toBeNull();
});

test('action stamps the event when the payment is missing', function (): void {
    $handled = app(ConfirmTourVnpayPaymentAction::class)->handle(
        $this->event->event_id,
        $this->payment->id + 999,
        makeTourIpnPayload(),
    );

    expect($handled)->toBeFalse()
        ->and($this->event->fresh()->processed_at)->not->toBeNull()
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::PENDING->value);
});
