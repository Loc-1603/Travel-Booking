<?php

use App\Actions\CreateBookingAction;
use App\DTOs\CreateBookingData;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $this->vendor = User::create([
        'name' => 'BC Vendor', 'email' => 'bc-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'BC Customer', 'email' => 'bc-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->stranger = User::create([
        'name' => 'BC Stranger', 'email' => 'bc-stranger@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'BC Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'BC Room',
        'capacity' => 2, 'base_price' => 500000, 'total_rooms' => 5,
    ]);
    $otherHotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'BC Other Hotel',
        'city' => 'Hue', 'country' => 'Vietnam', 'status' => 'active',
    ]);
    $this->otherRoom = Room::create([
        'hotel_id' => $otherHotel->id, 'name' => 'BC Other Room',
        'capacity' => 2, 'base_price' => 400000, 'total_rooms' => 5,
    ]);
});

/** Create a booking via the CreateBookingAction and return it. */
function bcBooking(object $ctx, array $overrides = []): Booking
{
    return app(CreateBookingAction::class)->handle(new CreateBookingData(...array_merge([
        'customerId' => $ctx->customer->id,
        'hotelId' => $ctx->hotel->id,
        'roomQuantities' => [$ctx->room->id => 1],
        'checkIn' => Carbon::today()->addDays(7)->toDateString(),
        'checkOut' => Carbon::today()->addDays(9)->toDateString(),
    ], $overrides)));
}

// ── preview ──────────────────────────────────────────────────────────────────

test('preview returns price breakdown for valid rooms', function (): void {
    $res = $this->actingAs($this->customer)->postJson('/api/v1/bookings/preview', [
        'hotel_id' => $this->hotel->id,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'rooms' => [['room_id' => $this->room->id, 'quantity' => 1]],
    ])->assertOk();

    expect((float) $res->json('data.subtotal'))->toBeGreaterThan(0)
        ->and((float) $res->json('data.total'))->toBeGreaterThan(0)
        ->and($res->json('data.currency'))->toBe('VND')
        ->and($res->json('data.coupon_applied'))->toBeFalse();
});

test('preview rejects room from another hotel', function (): void {
    $this->actingAs($this->customer)->postJson('/api/v1/bookings/preview', [
        'hotel_id' => $this->hotel->id,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'rooms' => [['room_id' => $this->otherRoom->id, 'quantity' => 1]],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_ROOM');
});

// ── store ────────────────────────────────────────────────────────────────────

test('store creates a pending payment booking', function (): void {
    $res = $this->actingAs($this->customer)->postJson('/api/v1/bookings', [
        'hotel_id' => $this->hotel->id,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'rooms' => [['room_id' => $this->room->id, 'quantity' => 1]],
    ])->assertStatus(201);

    $booking = Booking::find($res->json('data.booking.id'));
    expect($booking->status)->toBe(BookingStatus::PENDING_PAYMENT->value)
        ->and((float) $booking->total_price)->toBeGreaterThan(0);
});

test('store rejects room from another hotel with INVALID_ROOM', function (): void {
    $this->actingAs($this->customer)->postJson('/api/v1/bookings', [
        'hotel_id' => $this->hotel->id,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'rooms' => [['room_id' => $this->otherRoom->id, 'quantity' => 1]],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_ROOM');

    expect(Booking::count())->toBe(0);
});

test('guest cannot create booking', function (): void {
    $this->postJson('/api/v1/bookings', [
        'hotel_id' => $this->hotel->id,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'rooms' => [['room_id' => $this->room->id, 'quantity' => 1]],
    ])->assertUnauthorized();
});

// ── show ─────────────────────────────────────────────────────────────────────

test('owner can view booking; stranger cannot; unknown uuid returns 404', function (): void {
    $booking = bcBooking($this);

    $this->actingAs($this->customer)
        ->getJson("/api/v1/bookings/{$booking->uuid}")->assertOk()
        ->assertJsonPath('data.uuid', $booking->uuid);

    $this->actingAs($this->stranger)
        ->getJson("/api/v1/bookings/{$booking->uuid}")->assertForbidden();

    $this->actingAs($this->customer)
        ->getJson('/api/v1/bookings/nonexistent-uuid')->assertNotFound();
});

// ── index ────────────────────────────────────────────────────────────────────

test('customer sees own bookings in index', function (): void {
    $booking = bcBooking($this);

    $this->actingAs($this->customer)
        ->getJson('/api/v1/bookings')->assertOk()
        ->assertJsonPath('data.data.0.uuid', $booking->uuid);
});

test('index excludes other customers bookings', function (): void {
    bcBooking($this);

    $this->actingAs($this->stranger)
        ->getJson('/api/v1/bookings')->assertOk()
        ->assertJsonCount(0, 'data.data');
});

// ── cancel ───────────────────────────────────────────────────────────────────

test('owner cancels booking and inventory is released', function (): void {
    $booking = bcBooking($this);
    $date = Carbon::today()->addDays(7)->format('Y-m-d');

    // 1 room locked → 4 available
    expect((int) RoomAvailability::where('room_id', $this->room->id)
        ->whereDate('date', $date)->first()->available_rooms)->toBe(4);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$booking->uuid}/cancel")->assertOk()
        ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

    expect((int) RoomAvailability::where('room_id', $this->room->id)
        ->whereDate('date', $date)->first()->available_rooms)->toBe(5);
});

test('non-owner cannot cancel; double cancel is idempotent', function (): void {
    $booking = bcBooking($this);

    $this->actingAs($this->stranger)
        ->postJson("/api/v1/bookings/{$booking->uuid}/cancel")->assertForbidden();

    $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$booking->uuid}/cancel")->assertOk()
        ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

    // Second cancel: BookingService::cancelBooking returns early (already cancelled) — idempotent 200.
    $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$booking->uuid}/cancel")->assertOk()
        ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);
});

// ── createCheckoutSession ────────────────────────────────────────────────────

test('checkout session returns VNPay URL for pending booking', function (): void {
    $booking = bcBooking($this);

    $res = $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$booking->uuid}/checkout-session", [
            'network_token' => null,
        ])->assertOk();

    expect($res->json('data.checkout_url'))->toStartWith('https://sandbox.vnpayment.vn')
        ->and($res->json('data.txn_ref'))->not->toBeEmpty();

    $payment = Payment::find($res->json('data.payment_id'));
    expect($payment->status)->toBe('pending')
        ->and($payment->booking_id)->toBe($booking->id);
});

test('non-owner cannot create checkout session', function (): void {
    $booking = bcBooking($this);

    $this->actingAs($this->stranger)
        ->postJson("/api/v1/bookings/{$booking->uuid}/checkout-session", ['network_token' => null])
        ->assertForbidden();
});

// ── claim ────────────────────────────────────────────────────────────────────

test('guest booking can be claimed by matching email', function (): void {
    $guestBooking = bcBooking($this, [
        'customerId' => null,
        'guestEmail' => $this->customer->email,
        'guestName' => 'Guest User',
    ]);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$guestBooking->uuid}/claim")->assertOk()
        ->assertJsonPath('data.uuid', $guestBooking->uuid);

    $guestBooking->refresh();
    expect($guestBooking->customer_id)->toBe($this->customer->id)
        ->and($guestBooking->guest_email)->toBeNull();
});

test('claim with mismatched email returns 403', function (): void {
    $guestBooking = bcBooking($this, [
        'customerId' => null,
        'guestEmail' => 'someone-else@example.com',
        'guestName' => 'Other Guest',
    ]);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$guestBooking->uuid}/claim")
        ->assertForbidden()
        ->assertJsonPath('code', 'EMAIL_MISMATCH');
});

test('already-claimed booking returns 422', function (): void {
    $booking = bcBooking($this);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$booking->uuid}/claim")
        ->assertStatus(422)
        ->assertJsonPath('code', 'ALREADY_CLAIMED');
});

// ── storeDispute ─────────────────────────────────────────────────────────────

test('owner can open a dispute on a confirmed booking', function (): void {
    $booking = bcBooking($this);
    $booking->update(['status' => BookingStatus::CONFIRMED->value]);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$booking->uuid}/dispute", [
            'customer_notes' => 'The room was not clean when we arrived at the hotel.',
        ])->assertStatus(201);
});

test('dispute on pending payment booking returns 422', function (): void {
    $booking = bcBooking($this);

    $this->actingAs($this->customer)
        ->postJson("/api/v1/bookings/{$booking->uuid}/dispute", [
            'customer_notes' => 'The room was not clean when we arrived at the hotel.',
        ])->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_STATUS');
});

test('non-owner cannot open dispute', function (): void {
    $booking = bcBooking($this);
    $booking->update(['status' => BookingStatus::CONFIRMED->value]);

    $this->actingAs($this->stranger)
        ->postJson("/api/v1/bookings/{$booking->uuid}/dispute", [
            'customer_notes' => 'The room was not clean when we arrived at the hotel.',
        ])->assertForbidden();
});

// ── invoice ──────────────────────────────────────────────────────────────────

test('owner and vendor can access invoice; stranger cannot', function (): void {
    $booking = bcBooking($this);

    $this->actingAs($this->customer)
        ->get("/api/v1/bookings/{$booking->uuid}/invoice")->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

    $this->actingAs($this->vendor)
        ->get("/api/v1/bookings/{$booking->uuid}/invoice")->assertOk();

    $this->actingAs($this->stranger)
        ->get("/api/v1/bookings/{$booking->uuid}/invoice")->assertForbidden();
});
