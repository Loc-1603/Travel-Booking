<?php

use App\Models\Country;
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
        'name' => 'Super Admin', 'email' => 'tour-admin@test.local',
        'password' => bcrypt('password'), 'role' => 'super_admin', 'status' => 'active',
    ]);
    $this->superAdmin->assignRole('super-admin');
    $this->vendor = User::create([
        'name' => 'Tour Vendor', 'email' => 'tour-vendor-admin@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->vendor->assignRole('vendor');
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $this->countryId = $country->id;
    $this->province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Đà Lạt', 'slug' => 'da-lat-admin',
        'description' => 'Test', 'is_featured' => false,
    ]);
    $this->provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Admin Guide',
        'status' => 'pending',
    ]);
});

test('super admin manages tour provinces and providers', function (): void {
    $this->actingAs($this->superAdmin)->get('/admin/tour-provinces')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/tour-provinces/create')->assertOk();

    $this->actingAs($this->superAdmin)->post('/admin/tour-provinces', [
        'country_id' => $this->countryId, 'name' => 'Nha Trang', 'description' => 'Beach tours',
    ])->assertRedirect('/admin/tour-provinces');
    expect(TourProvince::where('name', 'Nha Trang')->exists())->toBeTrue();

    $this->actingAs($this->superAdmin)->get('/admin/tour-providers')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/tour-providers/'.$this->provider->id)->assertOk();

    $this->actingAs($this->superAdmin)
        ->post('/admin/tour-providers/'.$this->provider->id.'/approve')
        ->assertRedirect();
    expect($this->provider->fresh()->status)->toBe('approved');
});

test('super admin sees tour disputes and reviews queues', function (): void {
    $this->actingAs($this->superAdmin)->get('/admin/tour-disputes')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/tour-reviews')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/tour-attractions')->assertOk();
});

test('vendor manages own tours, slots, bookings inbox and messages', function (): void {
    $this->actingAs($this->vendor)->get('/admin/vendor/tours')->assertOk();
    $this->actingAs($this->vendor)->get('/admin/vendor/tours/create')->assertOk();

    $this->actingAs($this->vendor)->post('/admin/vendor/tours', [
        'provider_id' => $this->provider->id,
        'province_id' => $this->province->id,
        'title' => 'Vendor 1vs1 tour',
        'base_fixed' => 200000,
        'base_price_hourly' => 100000,
        'base_price_daily' => 800000,
    ])->assertRedirect('/admin/vendor/tours');

    $tour = TourProduct::where('title', 'Vendor 1vs1 tour')->first();
    expect($tour)->not->toBeNull()
        ->and((int) $tour->max_group_size)->toBe(1)
        ->and($tour->status)->toBe('draft');

    $this->actingAs($this->vendor)->get('/admin/vendor/tours/'.$tour->id.'/edit')->assertOk();
    $this->actingAs($this->vendor)->get('/admin/vendor/tours/'.$tour->id.'/slots')->assertOk();

    $this->actingAs($this->vendor)->post('/admin/vendor/tours/'.$tour->id.'/slots', [
        'date' => now()->addDay()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
    ])->assertRedirect('/admin/vendor/tours/'.$tour->id.'/slots');

    $this->actingAs($this->vendor)->get('/admin/vendor/tour-bookings')->assertOk();
    $this->actingAs($this->vendor)->get('/admin/vendor/tour-messages')->assertOk();
});

test('cross-vendor tour isolation in blade', function (): void {
    $other = User::create([
        'name' => 'Other Vendor', 'email' => 'other-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $other->assignRole('vendor');
    $tour = TourProduct::create([
        'provider_id' => $this->provider->id, 'province_id' => $this->province->id,
        'title' => 'Isolated tour', 'base_fixed' => 100000,
        'base_price_hourly' => 50000, 'base_price_daily' => 400000,
        'status' => 'draft',
    ]);

    // Other vendor cannot edit or view slots of a tour they do not own
    $this->actingAs($other)->get('/admin/vendor/tours/'.$tour->id.'/edit')->assertForbidden();
    $this->actingAs($other)->get('/admin/vendor/tours/'.$tour->id.'/slots')->assertForbidden();
    // Own list does not leak the other vendor tour
    $this->actingAs($other)->get('/admin/vendor/tours')
        ->assertOk()
        ->assertDontSee('Isolated tour', false);
});
