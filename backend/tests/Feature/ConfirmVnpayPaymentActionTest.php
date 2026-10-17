<?php

use App\Actions\ConfirmVnpayPaymentAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentConfirmed;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\VnpayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $vendor = User::create([
        'name' => 'Confirm Vendor', 'email' => 'confirm-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $customer = User::create([
        'name' => 'Confirm Customer', 'email' => 'confirm-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Confirm Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->booking = Booking::create([
        'customer_id' => $customer->id, 'hotel_id' => $hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1404000, 'currency' => 'VND',
    ]);
});

function confirmSignedIpn(Payment $payment, array $overrides = []): array
{
    $params = array_merge([
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_Amount' => (int) round((float) $payment->amount) * 100,
        'vnp_BankCode' => 'NCB',
        'vnp_CurrCode' => 'VND',
        'vnp_TxnRef' => $payment->external_id,
        'vnp_OrderInfo' => 'Thanh toan dat phong',
        'vnp_TransactionNo' => '14'.random_int(100000, 999999),
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_PayDate' => now()->format('YmdHis'),
    ], $overrides);
    $params['vnp_SecureHash'] = app(VnpayService::class)->signature($params);

    return $params;
}

function makeConfirmPayment(object $ctx, string $txnRef): Payment
{
    return Payment::create([
        'booking_id' => $ctx->booking->id, 'amount' => 1404000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => $txnRef,
        'status' => PaymentStatus::PENDING->value,
    ]);
}

function makeConfirmEvent(string $eventId): WebhookEvent
{
    return WebhookEvent::create(['provider' => 'vnpay', 'event_id' => $eventId]);
}

test('action confirms payment on 00/00 and stamps event', function (): void {
    Event::fake([PaymentConfirmed::class]);
    $payment = makeConfirmPayment($this, 'confirm-ok-1');
    $event = makeConfirmEvent('vnpay:confirm-ok-1:1001');
    $payload = confirmSignedIpn($payment, ['vnp_TransactionNo' => '1001']);

    $handled = app(ConfirmVnpayPaymentAction::class)->handle($event->event_id, $payment->id, $payload);

    expect($handled)->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED->value)
        ->and($event->fresh()->processed_at)->not->toBeNull()
        ->and($payment->fresh()->payload['vnp_transaction_no'])->toBe('1001');

    Event::assertDispatched(PaymentConfirmed::class);
});

test('action marks failed on error codes and keeps booking payable', function (): void {
    $payment = makeConfirmPayment($this, 'confirm-fail-1');
    $event = makeConfirmEvent('vnpay:confirm-fail-1:1002');
    $payload = confirmSignedIpn($payment, [
        'vnp_TransactionNo' => '1002', 'vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02',
    ]);

    $handled = app(ConfirmVnpayPaymentAction::class)->handle($event->event_id, $payment->id, $payload);

    expect($handled)->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::FAILED->value)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT->value)
        ->and($event->fresh()->processed_at)->not->toBeNull();
});

test('action skips processed events without touching payment', function (): void {
    $payment = makeConfirmPayment($this, 'confirm-skip-1');
    $event = makeConfirmEvent('vnpay:confirm-skip-1:1003');
    $event->update(['processed_at' => now()]);
    $payload = confirmSignedIpn($payment, ['vnp_TransactionNo' => '1003']);

    $handled = app(ConfirmVnpayPaymentAction::class)->handle($event->event_id, $payment->id, $payload);

    expect($handled)->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PENDING->value)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT->value);
});

test('action rejects bad signature and missing rows', function (): void {
    $payment = makeConfirmPayment($this, 'confirm-bad-1');
    $event = makeConfirmEvent('vnpay:confirm-bad-1:1004');
    $payload = confirmSignedIpn($payment, ['vnp_TransactionNo' => '1004']);
    $payload['vnp_SecureHash'] = 'deadbeef';

    expect(app(ConfirmVnpayPaymentAction::class)->handle($event->event_id, $payment->id, $payload))->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PENDING->value)
        ->and($event->fresh()->processed_at)->not->toBeNull();

    $missingEvent = app(ConfirmVnpayPaymentAction::class)->handle('vnpay:missing:0', $payment->id, $payload);
    expect($missingEvent)->toBeFalse();

    $event2 = makeConfirmEvent('vnpay:confirm-bad-1:1005');
    $missingPayment = app(ConfirmVnpayPaymentAction::class)->handle($event2->event_id, 999999, $payload);
    expect($missingPayment)->toBeFalse()
        ->and($event2->fresh()->processed_at)->not->toBeNull();
});
