<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Room;
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
        'name' => 'Vendor', 'email' => 'vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $customer = User::create([
        'name' => 'Customer', 'email' => 'customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Test Hotel VN',
        'city' => 'Hà Nội', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    Room::create([
        'hotel_id' => $hotel->id, 'name' => 'Standard Double',
        'capacity' => 2, 'base_price' => 650000, 'total_rooms' => 10,
    ]);

    $this->booking = Booking::create([
        'customer_id' => $customer->id, 'hotel_id' => $hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1404000, 'currency' => 'VND',
    ]);
});

function vnpay(): VnpayService
{
    return app(VnpayService::class);
}

/** Build a signed IPN payload for the given payment, like VNPay would send. */
function signedIpn(Payment $payment, array $overrides = []): array
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
    $params['vnp_SecureHash'] = vnpay()->signature($params);

    return $params;
}

test('vnpay payment url is created for a pending booking', function (): void {
    $result = vnpay()->createPaymentUrl($this->booking, '127.0.0.1');

    expect($result['payment_url'])->toStartWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?')
        ->and($result['payment_url'])->toContain('vnp_TmnCode=TESTCODE')
        ->and($result['txn_ref'])->not->toBeEmpty();

    $payment = Payment::find($result['payment_id']);
    expect($payment->provider)->toBe('vnpay')
        ->and($payment->status)->toBe(PaymentStatus::PENDING->value)
        ->and($payment->currency)->toBe('VND')
        ->and($payment->external_id)->toBe($result['txn_ref']);
});

test('vnp_Amount equals VND amount times 100', function (): void {
    $result = vnpay()->createPaymentUrl($this->booking, '127.0.0.1');

    parse_str((string) parse_url($result['payment_url'], PHP_URL_QUERY), $query);

    expect((int) $query['vnp_Amount'])->toBe(1404000 * 100)
        ->and($query['vnp_CurrCode'])->toBe('VND');
});

test('valid signature verifies successfully', function (): void {
    $result = vnpay()->createPaymentUrl($this->booking, '127.0.0.1');

    parse_str((string) parse_url($result['payment_url'], PHP_URL_QUERY), $query);

    expect(vnpay()->verifySignature($query))->toBeTrue();
});

test('tampered signature or amount fails verification', function (): void {
    $result = vnpay()->createPaymentUrl($this->booking, '127.0.0.1');

    parse_str((string) parse_url($result['payment_url'], PHP_URL_QUERY), $query);

    $tamperedAmount = $query;
    $tamperedAmount['vnp_Amount'] = 1000;
    expect(vnpay()->verifySignature($tamperedAmount))->toBeFalse();

    $tamperedHash = $query;
    $tamperedHash['vnp_SecureHash'] = 'deadbeef';
    expect(vnpay()->verifySignature($tamperedHash))->toBeFalse();

    $missingHash = $query;
    unset($missingHash['vnp_SecureHash']);
    expect(vnpay()->verifySignature($missingHash))->toBeFalse();
});

test('successful ipn confirms payment and booking', function (): void {
    Event::fake([App\Events\PaymentConfirmed::class]);

    $payment = Payment::create([
        'booking_id' => $this->booking->id, 'amount' => 1404000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'ipn-success-1',
        'status' => PaymentStatus::PENDING->value,
    ]);

    $response = $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query(signedIpn($payment)));

    $response->assertOk()->assertJson(['RspCode' => '00']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::CONFIRMED->value);

    Event::assertDispatched(App\Events\PaymentConfirmed::class);
});

test('replayed ipn is not processed twice', function (): void {
    $payment = Payment::create([
        'booking_id' => $this->booking->id, 'amount' => 1404000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'ipn-replay-1',
        'status' => PaymentStatus::PENDING->value,
    ]);
    $query = http_build_query($params = signedIpn($payment));

    $this->getJson('/api/v1/payments/vnpay-ipn?'.$query)->assertOk()->assertJson(['RspCode' => '00']);

    $eventsAfterFirst = WebhookEvent::where('provider', 'vnpay')->count();
    $processedAt = WebhookEvent::where('provider', 'vnpay')->first()->processed_at;

    // Same IPN delivered again (same TxnRef + TransactionNo).
    $second = $this->getJson('/api/v1/payments/vnpay-ipn?'.$query)->assertOk();

    expect(WebhookEvent::where('provider', 'vnpay')->count())->toBe($eventsAfterFirst)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value)
        ->and($second->json('RspCode'))->toBeIn(['00', '02']);
});

test('failed ipn marks payment failed and keeps booking payable', function (): void {
    $payment = Payment::create([
        'booking_id' => $this->booking->id, 'amount' => 1404000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'ipn-fail-1',
        'status' => PaymentStatus::PENDING->value,
    ]);

    // User cancelled on VNPay (ResponseCode 24).
    $response = $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query(
        signedIpn($payment, ['vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02', 'vnp_TransactionNo' => '14999999'])
    ));

    $response->assertOk()->assertJson(['RspCode' => '00']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED->value)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT->value);
});

test('response code 00 without success transaction status does not confirm', function (): void {
    // Guards against naive "ResponseCode == 00 => paid" handling of return-style params.
    $payment = Payment::create([
        'booking_id' => $this->booking->id, 'amount' => 1404000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'ipn-nostatus-1',
        'status' => PaymentStatus::PENDING->value,
    ]);

    $params = signedIpn($payment);
    unset($params['vnp_TransactionStatus']);
    $params['vnp_SecureHash'] = vnpay()->signature($params);

    $this->getJson('/api/v1/payments/vnpay-ipn?'.http_build_query($params))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED->value)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT->value);
});

test('two payment attempts in the same second have different txn refs', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 9, 9, 12, 0, 0));

    $first = vnpay()->createPaymentUrl($this->booking, '127.0.0.1');
    $second = vnpay()->createPaymentUrl($this->booking, '127.0.0.1');

    Carbon::setTestNow();

    expect($first['txn_ref'])->not->toBe($second['txn_ref'])
        ->and(Payment::where('external_id', $first['txn_ref'])->count())->toBe(1)
        ->and(Payment::where('external_id', $second['txn_ref'])->count())->toBe(1)
        ->and(vnpay()->findPaymentForIpn(['vnp_TxnRef' => $second['txn_ref']])->id)->toBe($second['payment_id']);
});
