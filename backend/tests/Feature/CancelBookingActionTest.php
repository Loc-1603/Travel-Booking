<?php

use App\Actions\CancelBookingAction;
use App\Actions\CreateBookingAction;
use App\DTOs\CreateBookingData;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $vendor = User::create([
        'name' => 'Cancel Vendor', 'email' => 'cancel-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Cancel Customer', 'email' => 'cancel-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Cancel Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Cancel Room',
        'capacity' => 2, 'base_price' => 500000, 'total_rooms' => 5,
    ]);
});

function makeActionBooking(object $ctx, array $hotelOverrides = []): Booking
{
    if ($hotelOverrides !== []) {
        $ctx->hotel->update($hotelOverrides);
    }

    return app(CreateBookingAction::class)->handle(new CreateBookingData(
        customerId: $ctx->customer->id,
        hotelId: $ctx->hotel->id,
        roomQuantities: [$ctx->room->id => 1],
        checkIn: Carbon::today()->addDays(7)->toDateString(),
        checkOut: Carbon::today()->addDays(9)->toDateString(),
    ));
}

function addCompletedPayment(Booking $booking, float $amount): Payment
{
    return Payment::create([
        'booking_id' => $booking->id, 'amount' => $amount, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'cancel-txn-'.uniqid(),
        'status' => PaymentStatus::COMPLETED->value,
    ]);
}

function enableHotelCancelRefunds(): void
{
    config([
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'cancel-secret',
    ]);
}

test('action cancels booking and releases inventory', function (): void {
    $booking = makeActionBooking($this);
    $date = Carbon::today()->addDays(7)->format('Y-m-d');

    expect((int) RoomAvailability::where('room_id', $this->room->id)->whereDate('date', $date)->first()->available_rooms)->toBe(4);

    $result = app(CancelBookingAction::class)->handle($booking->load('payments'));

    expect($result->status)->toBe(BookingStatus::CANCELLED->value)
        ->and((int) RoomAvailability::where('room_id', $this->room->id)->whereDate('date', $date)->first()->available_rooms)->toBe(5);
});

test('action refunds completed payment under free cancellation', function (): void {
    $booking = makeActionBooking($this, [
        'cancellation_policy' => ['type' => 'free_until_hours', 'hours' => 24],
    ]);
    $payment = addCompletedPayment($booking, (float) $booking->total_price);

    $result = app(CancelBookingAction::class)->handle($booking->load('payments'));

    expect($result->status)->toBe(BookingStatus::CANCELLED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe((float) $payment->amount)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::REFUNDED->value);
});

test('action keeps payment untouched under non-refundable policy', function (): void {
    $booking = makeActionBooking($this, [
        'cancellation_policy' => ['type' => 'non_refundable'],
    ]);
    $payment = addCompletedPayment($booking, (float) $booking->total_price);

    $result = app(CancelBookingAction::class)->handle($booking->load('payments'));

    expect($result->status)->toBe(BookingStatus::CANCELLED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value);
});

test('action skips payments without external id', function (): void {
    $booking = makeActionBooking($this, [
        'cancellation_policy' => ['type' => 'free_until_hours', 'hours' => 24],
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id, 'amount' => (float) $booking->total_price, 'currency' => 'VND',
        'provider' => 'internal', 'external_id' => null,
        'status' => PaymentStatus::COMPLETED->value,
    ]);

    $result = app(CancelBookingAction::class)->handle($booking->load('payments'));

    expect($result->status)->toBe(BookingStatus::CANCELLED->value)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('action creates one idempotent vnpay obligation when refunds are enabled', function (): void {
    enableHotelCancelRefunds();
    Queue::fake();

    $booking = makeActionBooking($this, [
        'cancellation_policy' => ['type' => 'free_until_hours', 'hours' => 24],
    ]);
    $payment = addCompletedPayment($booking, (float) $booking->total_price);
    $payment->update(['payload' => [
        'txn_ref' => $payment->external_id,
        'vnp_transaction_no' => '15713760',
        'vnp_create_date' => '20261024101010',
    ]]);

    app(CancelBookingAction::class)->handle($booking->load('payments'));
    app(CancelBookingAction::class)->handle($booking->fresh()->load('payments'));

    $refunds = PaymentRefund::all();

    expect($refunds)->toHaveCount(1)
        ->and($refunds[0]->status)->toBe('pending')
        ->and($refunds[0]->type)->toBe('02')
        ->and((float) $refunds[0]->amount)->toBe((float) $booking->total_price)
        ->and($refunds[0]->idempotency_key)->toBe('hotel-cancel:'.$booking->id.':'.$payment->id)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('action creates a partial obligation for a percentage policy', function (): void {
    enableHotelCancelRefunds();
    Queue::fake();

    $booking = makeActionBooking($this, [
        'cancellation_policy' => [
            'type' => 'rules',
            'rules' => [['hours_before' => 1, 'refund_percent' => 50]],
        ],
    ]);
    $payment = addCompletedPayment($booking, (float) $booking->total_price);
    $payment->update(['payload' => [
        'txn_ref' => $payment->external_id,
        'vnp_transaction_no' => '15713760',
        'vnp_create_date' => '20261024101010',
    ]]);

    app(CancelBookingAction::class)->handle($booking->load('payments'));

    $refund = PaymentRefund::first();
    $expected = round((float) $booking->total_price * 0.5, 2);

    expect($refund)->not->toBeNull()
        ->and((float) $refund->amount)->toBe($expected)
        ->and($refund->type)->toBe('03')
        ->and($refund->status)->toBe('pending')
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0);
});

test('action spreads the refund across multiple completed payments', function (): void {
    enableHotelCancelRefunds();
    Queue::fake();

    $booking = makeActionBooking($this, [
        'cancellation_policy' => [
            'type' => 'rules',
            'rules' => [['hours_before' => 1, 'refund_percent' => 50]],
        ],
    ]);
    $total = (float) $booking->total_price;
    $first = addCompletedPayment($booking, round($total * 0.4, 2));
    $second = addCompletedPayment($booking, round($total * 0.6, 2));

    foreach ([$first, $second] as $payment) {
        $payment->update(['payload' => [
            'txn_ref' => $payment->external_id,
            'vnp_transaction_no' => '1571376'.$payment->id,
            'vnp_create_date' => '20261024101010',
        ]]);
    }

    app(CancelBookingAction::class)->handle($booking->fresh()->load('payments'));

    $refunds = PaymentRefund::orderBy('id')->get();

    expect($refunds)->toHaveCount(2)
        ->and($refunds[0]->payment_id)->toBe($first->id)
        ->and((float) $refunds[0]->amount)->toBe(round($total * 0.4, 2))
        ->and($refunds[0]->type)->toBe('02')
        ->and($refunds[0]->idempotency_key)->toBe('hotel-cancel:'.$booking->id.':'.$first->id)
        ->and($refunds[1]->payment_id)->toBe($second->id)
        ->and((float) $refunds[1]->amount)->toBe(round($total * 0.1, 2))
        ->and($refunds[1]->type)->toBe('03')
        ->and($refunds[1]->idempotency_key)->toBe('hotel-cancel:'.$booking->id.':'.$second->id)
        ->and((float) $first->fresh()->refunded_amount)->toBe(0.0)
        ->and((float) $second->fresh()->refunded_amount)->toBe(0.0);
});

test('action creates no obligation under a non-refundable policy when refunds are enabled', function (): void {
    enableHotelCancelRefunds();
    Queue::fake();

    $booking = makeActionBooking($this, [
        'cancellation_policy' => ['type' => 'non_refundable'],
    ]);
    $payment = addCompletedPayment($booking, (float) $booking->total_price);
    $payment->update(['payload' => [
        'txn_ref' => $payment->external_id,
        'vnp_transaction_no' => '15713760',
        'vnp_create_date' => '20261024101010',
    ]]);

    $result = app(CancelBookingAction::class)->handle($booking->load('payments'));

    expect($result->status)->toBe(BookingStatus::CANCELLED->value)
        ->and(PaymentRefund::count())->toBe(0)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value);
});
