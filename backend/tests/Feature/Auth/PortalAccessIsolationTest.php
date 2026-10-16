<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\VendorProfile;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'guide', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create(['role' => Role::ADMIN, 'status' => 'active']);
    $this->admin->assignRole('admin');

    $this->superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN, 'status' => 'active']);
    $this->superAdmin->assignRole('super-admin');

    $this->guide = User::factory()->create(['role' => Role::GUIDE, 'status' => 'active']);
    $this->guide->assignRole('guide');

    $this->vendor = User::factory()->create(['role' => Role::VENDOR, 'status' => 'active']);
    $this->vendor->assignRole('vendor');
    $this->vendor->vendorProfile()->create(['status' => VendorProfile::STATUS_APPROVED]);

    $this->pendingVendor = User::factory()->create(['role' => Role::VENDOR, 'status' => 'active']);
    $this->pendingVendor->assignRole('vendor');
    $this->pendingVendor->vendorProfile()->create(['status' => VendorProfile::STATUS_PENDING]);
});

test('guide can access the tour cluster, dashboard, payouts and support', function () {
    foreach ([
        'admin.vendor.dashboard',
        'admin.vendor.guide-profile.index',
        'admin.vendor.tour-bookings.index',
        'admin.vendor.tour-messages.index',
        'admin.vendor.payouts.index',
        'admin.vendor.support-tickets.index',
    ] as $name) {
        $this->actingAs($this->guide)->get(route($name))->assertOk();
    }
});

test('guide is forbidden from the hotel cluster and reports', function () {
    foreach ([
        'admin.vendor.hotels.index',
        'admin.vendor.rooms.index',
        'admin.vendor.bookings.index',
        'admin.vendor.reports.index',
    ] as $name) {
        $this->actingAs($this->guide)->get(route($name))->assertForbidden();
    }
});

test('guide visiting the admin dashboard is redirected to the vendor dashboard', function () {
    $this->actingAs($this->guide)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.vendor.dashboard'));
});

test('guide reaching level-1 admin pages reflects the documented risk 2 exposure', function () {
    // Risk #2 (out of scope): AdminMiddleware admits guides, and users/roles
    // have no inner guard — the same exposure vendors had before this change.
    // Hardening is tracked as a separate follow-up; assert the actual behavior.
    $this->actingAs($this->guide)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($this->guide)->get(route('admin.roles.index'))->assertOk();
});

test('vendor is forbidden from the tour cluster', function () {
    foreach ([
        'admin.vendor.guide-profile.index',
        'admin.vendor.tour-bookings.index',
        'admin.vendor.tour-messages.index',
    ] as $name) {
        $this->actingAs($this->vendor)->get(route($name))->assertForbidden();
    }
});

test('approved vendor reaches the hotel cluster and reports', function () {
    $this->actingAs($this->vendor)->get(route('admin.vendor.hotels.index'))->assertOk();
    $this->actingAs($this->vendor)->get(route('admin.vendor.reports.index'))->assertOk();
});

test('pending vendor still sees the approval notice on hotel pages', function () {
    $this->actingAs($this->pendingVendor)
        ->get(route('admin.vendor.hotels.index'))
        ->assertOk()
        ->assertSee(__('admin.vendor.pending_approval_feature'), false);
});

test('admins keep their own area unchanged', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($this->superAdmin)->get(route('admin.vendors.index'))->assertOk();
});
