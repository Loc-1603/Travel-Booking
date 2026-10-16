<?php

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

    $this->vendor = User::factory()->create([
        'name' => 'Report Vendor', 'email' => 'report-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->vendor->assignRole('vendor');
    VendorProfile::create([
        'user_id' => $this->vendor->id,
        'status' => VendorProfile::STATUS_APPROVED,
    ]);

    $this->customer = User::factory()->create([
        'name' => 'Report Customer', 'email' => 'report-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);

    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Report Hotel',
        'city' => 'Hà Nội', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Deluxe',
        'capacity' => 2, 'base_price' => 800000, 'total_rooms' => 5,
    ]);

    // Completed stay inside the current month: counts in revenue + room-type chart.
    $completed = Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => 'completed',
        'check_in' => Carbon::today()->subDays(5)->toDateString(),
        'check_out' => Carbon::today()->subDays(2)->toDateString(),
        'total_price' => 2400000, 'currency' => 'VND',
    ]);
    // Confirmed stay: counts in occupancy only.
    $confirmed = Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => 'confirmed',
        'check_in' => Carbon::today()->subDays(1)->toDateString(),
        'check_out' => Carbon::today()->addDay()->toDateString(),
        'total_price' => 1600000, 'currency' => 'VND',
    ]);
    foreach ([$completed, $confirmed] as $booking) {
        DB::table('booking_rooms')->insert([
            'booking_id' => $booking->id, 'room_id' => $this->room->id,
            'quantity' => 1, 'unit_price' => 800000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});

test('vendor reports page renders on any db driver', function (): void {
    $response = $this->actingAs($this->vendor)->get('/admin/vendor/reports')->assertOk();

    // Revenue by room type: 1 * 800000 * 3 nights = 2.400.000 (completed only).
    $response->assertSee('2.400.000', false);
});

test('vendor report export streams a csv', function (): void {
    $response = $this->actingAs($this->vendor)->get('/admin/vendor/reports/export')->assertOk();

    expect($response->streamedContent())->toContain('Revenue by Room Type')
        ->toContain('Deluxe')
        ->toContain('Occupancy');
});
