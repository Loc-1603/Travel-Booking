<?php

use App\Actions\CancelBookingAction;
use App\Actions\CreateBookingAction;
use App\DTOs\CreateBookingData;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\User;
use Carbon\Carbon;

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
