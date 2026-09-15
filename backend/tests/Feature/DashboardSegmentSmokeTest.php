<?php

use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvince;
use App\Models\TourProvider;
use App\Models\User;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'vendor', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    $this->superAdmin = User::create([
        'name' => 'SA', 'email' => 'dash-sa@test.local',
        'password' => bcrypt('password'), 'role' => 'super_admin', 'status' => 'active',
    ]);
    $this->superAdmin->assignRole('super-admin');
    $this->vendor = User::create([
        'name' => 'Tour Only Vendor', 'email' => 'dash-tour-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->vendor->assignRole('vendor');
    $this->customer = User::create([
        'name' => 'Cust', 'email' => 'dash-cust@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create(['country_id' => $country->id, 'name' => 'Hue', 'slug' => 'hue-dash', 'is_featured' => false]);
    $provider = TourProvider::create(['vendor_id' => $this->vendor->id, 'business_name' => 'Dash Provider', 'status' => 'approved']);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Dash Tour', 'base_fixed' => 100000, 'base_price_hourly' => 50000,
        'base_price_daily' => 400000, 'status' => 'published',
    ]);
    $slotPast = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => now()->subDays(2)->toDateString(),
        'start_time' => '08:00', 'end_time' => '11:00', 'status' => 'booked',
    ]);
    $slotFuture = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => now()->addDays(2)->toDateString(),
        'start_time' => '08:00', 'end_time' => '11:00', 'status' => 'booked',
    ]);
    $slotOld = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => now()->subDays(10)->toDateString(),
        'start_time' => '08:00', 'end_time' => '11:00', 'status' => 'booked',
    ]);
    $slotCancelled = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => now()->subDays(5)->toDateString(),
        'start_time' => '08:00', 'end_time' => '11:00', 'status' => 'available',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $tour->id, 'provider_id' => $provider->id, 'province_id' => $province->id,
        'slot_id' => $slotPast->id,
        'start_at' => now()->subDays(2), 'end_at' => now()->subDays(2)->addHours(3),
        'total_price' => 500000, 'currency' => 'VND', 'status' => 'confirmed',
    ]);
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $tour->id, 'provider_id' => $provider->id, 'province_id' => $province->id,
        'slot_id' => $slotFuture->id,
        'start_at' => now()->addDays(2), 'end_at' => now()->addDays(2)->addHours(3),
        'total_price' => 300000, 'currency' => 'VND', 'status' => 'pending_payment',
    ]);
    // Past tour auto-completed: must still count as revenue.
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $tour->id, 'provider_id' => $provider->id, 'province_id' => $province->id,
        'slot_id' => $slotOld->id,
        'start_at' => now()->subDays(10), 'end_at' => now()->subDays(10)->addHours(3),
        'total_price' => 400000, 'currency' => 'VND', 'status' => 'completed',
    ]);
    // Cancelled: counts in booking total, never in revenue.
    TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $tour->id, 'provider_id' => $provider->id, 'province_id' => $province->id,
        'slot_id' => $slotCancelled->id,
        'start_at' => now()->subDays(5), 'end_at' => now()->subDays(5)->addHours(3),
        'total_price' => 200000, 'currency' => 'VND', 'status' => 'cancelled',
    ]);
});

test('vendor with no hotels auto-sees tour segment', function (): void {
    // No ?segment: auto => tour (vendor has provider, no hotels)
    $this->actingAs($this->vendor)->get('/admin/vendor/dashboard')
        ->assertOk()
        ->assertSee(__('admin.total_tour_bookings'), false)
        ->assertSee('segment=tour', false);
});

test('vendor explicit hotel segment still works', function (): void {
    $this->actingAs($this->vendor)->get('/admin/vendor/dashboard?segment=hotel')
        ->assertOk()
        ->assertSee(__('admin.total_bookings'), false);
});

test('vendor tour segment shows only own tour revenue', function (): void {
    $this->actingAs($this->vendor)->get('/admin/vendor/dashboard?segment=tour')
        ->assertOk()
        ->assertSee('400.000', false); // only completed 400k; confirmed/pending/cancelled excluded
});

test('super admin toggles both segments', function (): void {
    $this->actingAs($this->superAdmin)->get('/admin/dashboard?segment=hotel')
        ->assertOk()
        ->assertSee(__('admin.total_hotels'), false);
    $this->actingAs($this->superAdmin)->get('/admin/dashboard?segment=tour')
        ->assertOk()
        ->assertSee(__('admin.total_tours'), false);
});

