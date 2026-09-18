<?php

use App\Models\VendorProfile;
use App\Models\User;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    $this->pendingVendor = User::factory()->create([
        'name' => 'Pending Vendor', 'email' => 'pending-access@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->pendingVendor->assignRole('vendor');
    $this->pendingVendor->vendorProfile()->create(['status' => VendorProfile::STATUS_PENDING]);

    $this->approvedVendor = User::factory()->create([
        'name' => 'Approved Vendor', 'email' => 'approved-access@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->approvedVendor->assignRole('vendor');
    $this->approvedVendor->vendorProfile()->create(['status' => VendorProfile::STATUS_APPROVED]);
});

test('pending vendor sees the friendly approval notice instead of a 403 on hotel pages', function () {
    foreach (['hotels', 'rooms', 'bookings'] as $path) {
        $this->actingAs($this->pendingVendor)
            ->get('/admin/vendor/'.$path)
            ->assertOk()
            ->assertSee(__('admin.vendor.pending_approval_feature'), false)
            ->assertDontSee(__('admin.vendor.hotels.title'), false);
    }
});

test('pending vendor is redirected from hotel-cluster POST actions', function () {
    $this->actingAs($this->pendingVendor)
        ->post('/admin/vendor/hotels', ['name' => 'Nope'])
        ->assertRedirect(route('admin.vendor.dashboard'));
});

test('approved vendor can access hotel pages', function () {
    $this->actingAs($this->approvedVendor)
        ->get('/admin/vendor/hotels')
        ->assertOk()
        ->assertSee(__('admin.vendor.hotels.title'), false);

    $this->actingAs($this->approvedVendor)
        ->get('/admin/vendor/rooms')
        ->assertOk();

    $this->actingAs($this->approvedVendor)
        ->get('/admin/vendor/bookings')
        ->assertOk();
});

test('guide-profile stays unlocked for a pending vendor', function () {
    $this->actingAs($this->pendingVendor)
        ->get('/admin/vendor/guide-profile')
        ->assertOk()
        ->assertSee(__('admin.vendor.guide_profiles.my_title'), false);
});