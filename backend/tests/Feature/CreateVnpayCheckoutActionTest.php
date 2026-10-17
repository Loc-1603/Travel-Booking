<?php

use App\Actions\CreateVnpayCheckoutAction;
use App\DTOs\CheckoutData;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $vendor = User::create([
        'name' => 'Checkout Vendor', 'email' => 'checkout-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $customer = User::create([
        'name' => 'Checkout Customer', 'email' => 'checkout-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Checkout Hotel',
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

test('action builds checkout session for pending booking', function (): void {
    $result = app(CreateVnpayCheckoutAction::class)->handle(
        new CheckoutData(bookingId: $this->booking->id, ipAddr: '127.0.0.1', bankCode: 'NCB')
    );

    expect($result['checkout_url'])->toStartWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?')
        ->and($result['checkout_url'])->toContain('vnp_TmnCode=TESTCODE')
        ->and($result['txn_ref'])->not->toBeEmpty();

    $payment = Payment::find($result['payment_id']);
    expect($payment->provider)->toBe('vnpay')
        ->and($payment->status)->toBe(PaymentStatus::PENDING->value)
        ->and($payment->external_id)->toBe($result['txn_ref']);

    parse_str((string) parse_url($result['checkout_url'], PHP_URL_QUERY), $query);
    expect((int) $query['vnp_Amount'])->toBe(1404000 * 100);
});

test('action rejects missing or non-pending bookings', function (): void {
    expect(fn () => app(CreateVnpayCheckoutAction::class)->handle(new CheckoutData(bookingId: 999999)))
        ->toThrow(InvalidArgumentException::class, 'Booking not found');

    $this->booking->update(['status' => BookingStatus::CONFIRMED->value]);

    expect(fn () => app(CreateVnpayCheckoutAction::class)->handle(new CheckoutData(bookingId: $this->booking->id)))
        ->toThrow(RuntimeException::class, 'not awaiting payment');
});
