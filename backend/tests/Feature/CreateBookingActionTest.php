<?php

use App\Actions\CreateBookingAction;
use App\DTOs\CreateBookingData;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    $vendor = User::create([
        'name' => 'Action Vendor', 'email' => 'action-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Action Customer', 'email' => 'action-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Action Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Action Room',
        'capacity' => 2, 'base_price' => 500000, 'total_rooms' => 5,
    ]);
    $otherHotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Other Hotel',
        'city' => 'Hue', 'country' => 'Vietnam', 'status' => 'active',
    ]);
    $this->otherRoom = Room::create([
        'hotel_id' => $otherHotel->id, 'name' => 'Other Room',
        'capacity' => 2, 'base_price' => 400000, 'total_rooms' => 5,
    ]);
});

function bookingData(object $ctx, array $overrides = []): CreateBookingData
{
    return new CreateBookingData(...array_merge([
        'customerId' => $ctx->customer->id,
        'hotelId' => $ctx->hotel->id,
        'roomQuantities' => [$ctx->room->id => 2],
        'checkIn' => Carbon::today()->addDays(7)->toDateString(),
        'checkOut' => Carbon::today()->addDays(9)->toDateString(),
    ], $overrides));
}

test('action creates booking with pricing and locks inventory', function (): void {
    $booking = app(CreateBookingAction::class)->handle(bookingData($this));

    expect($booking->status)->toBe(BookingStatus::PENDING_PAYMENT->value)
        ->and((float) $booking->total_price)->toBeGreaterThan(0)
        ->and($booking->bookingRooms->count())->toBe(1)
        ->and((int) $booking->bookingRooms->first()->quantity)->toBe(2);

    $date = Carbon::today()->addDays(7)->format('Y-m-d');
    $left = \App\Models\RoomAvailability::where('room_id', $this->room->id)
        ->whereDate('date', $date)->first();
    expect((int) $left->available_rooms)->toBe(3);
});

test('action rejects rooms from another hotel', function (): void {
    $data = bookingData($this, ['roomQuantities' => [$this->otherRoom->id => 1]]);

    expect(fn () => app(CreateBookingAction::class)->handle($data))
        ->toThrow(InvalidArgumentException::class, 'Room does not belong');

    expect(Booking::count())->toBe(0);
});

test('action keeps guest and customer rules', function (): void {
    $guest = bookingData($this, [
        'customerId' => null, 'guestEmail' => 'guest@example.com', 'guestName' => 'Guest',
    ]);
    $booking = app(CreateBookingAction::class)->handle($guest);
    expect($booking->guest_email)->toBe('guest@example.com');

    $badGuest = bookingData($this, ['customerId' => null]);
    expect(fn () => app(CreateBookingAction::class)->handle($badGuest))
        ->toThrow(InvalidArgumentException::class);

    $both = bookingData($this, ['guestEmail' => 'g@example.com', 'guestName' => 'G']);
    expect(fn () => app(CreateBookingAction::class)->handle($both))
        ->toThrow(InvalidArgumentException::class);
});

test('action fails cleanly when availability is exhausted', function (): void {
    $data = bookingData($this, ['roomQuantities' => [$this->room->id => 99]]);
    $date = Carbon::today()->addDays(7)->format('Y-m-d');

    // Seed via the same path as the Action (CarbonPeriod + Y-m-d) so the
    // "insufficient" check triggers instead of a duplicate insert race.
    app(\App\Services\AvailabilityService::class)
        ->ensureAvailabilityRows($this->room->id, $data->checkIn, $data->checkOut);
    expect(fn () => app(CreateBookingAction::class)->handle($data))
        ->toThrow(RuntimeException::class, 'Insufficient availability');

    expect(Booking::count())->toBe(0);
    $row = \App\Models\RoomAvailability::where('room_id', $this->room->id)
        ->whereDate('date', $date)->first();
    expect((int) $row->available_rooms)->toBe(5);
});
