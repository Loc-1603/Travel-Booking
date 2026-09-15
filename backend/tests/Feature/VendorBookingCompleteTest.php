<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

    $this->vendor = User::create([
        'name' => 'Vendor', 'email' => 'vendor-complete@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->vendor->assignRole('vendor');

    $this->otherVendor = User::create([
        'name' => 'Other Vendor', 'email' => 'other-vendor-complete@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->otherVendor->assignRole('vendor');

    $this->customer = User::create([
        'name' => 'Customer', 'email' => 'customer-complete@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);

    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Complete Hotel',
        'city' => 'Hà Nội', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Standard',
        'capacity' => 2, 'base_price' => 650000, 'total_rooms' => 10,
    ]);

    $this->otherHotel = Hotel::create([
        'vendor_id' => $this->otherVendor->id, 'name' => 'Other Hotel',
        'city' => 'Huế', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
});

function makeCompleteTestBooking(int $hotelId, int $customerId, string $status): Booking
{
    return Booking::create([
        'customer_id' => $customerId, 'hotel_id' => $hotelId,
        'status' => $status,
        'check_in' => Carbon::today()->subDays(5)->toDateString(),
        'check_out' => Carbon::today()->subDays(3)->toDateString(),
        'total_price' => 1404000, 'currency' => 'VND',
    ]);
}

test('vendor marks own confirmed booking as completed', function (): void {
    $booking = makeCompleteTestBooking($this->hotel->id, $this->customer->id, BookingStatus::CONFIRMED->value);

    $this->actingAs($this->vendor)
        ->post('/admin/vendor/bookings/'.$booking->uuid.'/complete')
        ->assertRedirect('/admin/vendor/bookings');

    expect($booking->fresh()->status)->toBe(BookingStatus::COMPLETED->value);
});

test('vendor cannot complete a pending payment booking', function (): void {
    $booking = makeCompleteTestBooking($this->hotel->id, $this->customer->id, BookingStatus::PENDING_PAYMENT->value);

    $this->actingAs($this->vendor)
        ->post('/admin/vendor/bookings/'.$booking->uuid.'/complete')
        ->assertRedirect('/admin/vendor/bookings');

    expect($booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT->value);
});

test('vendor cannot complete another vendor booking', function (): void {
    $booking = makeCompleteTestBooking($this->otherHotel->id, $this->customer->id, BookingStatus::CONFIRMED->value);

    $this->actingAs($this->vendor)
        ->post('/admin/vendor/bookings/'.$booking->uuid.'/complete')
        ->assertForbidden();

    expect($booking->fresh()->status)->toBe(BookingStatus::CONFIRMED->value);
});
