<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\VendorProfile;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'guide', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN, 'status' => 'active']);
    $this->superAdmin->assignRole('super-admin');

    $this->vendor = User::factory()->create(['role' => Role::VENDOR, 'status' => 'active']);
    $this->vendor->assignRole('vendor');
    $this->vendor->vendorProfile()->create(['status' => VendorProfile::STATUS_APPROVED]);

    $this->guide = User::factory()->create(['role' => Role::GUIDE, 'status' => 'active']);
    $this->guide->assignRole('guide');
});

test('guide sidebar shows the tour cluster and hides the hotel cluster', function () {
    $this->actingAs($this->guide)
        ->get(route('admin.vendor.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.sidebar.tour_management'), false)
        ->assertSee(__('admin.sidebar.guide_profiles'), false)
        ->assertSee(route('admin.vendor.guide-profile.index'), false)
        ->assertSee(route('admin.vendor.tour-bookings.index'), false)
        ->assertSee(route('admin.vendor.tour-messages.index'), false)
        ->assertDontSee(__('admin.sidebar.hotel_management'), false)
        ->assertDontSee(route('admin.vendor.hotels.index'), false)
        ->assertDontSee(route('admin.vendor.reports.index'), false);
});

test('vendor sidebar shows the hotel cluster and hides the tour cluster', function () {
    $this->actingAs($this->vendor)
        ->get(route('admin.vendor.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.sidebar.hotel_management'), false)
        ->assertSee(route('admin.vendor.hotels.index'), false)
        ->assertSee(route('admin.vendor.reports.index'), false)
        ->assertDontSee(__('admin.sidebar.tour_management'), false)
        ->assertDontSee(route('admin.vendor.guide-profile.index'), false)
        ->assertDontSee(route('admin.vendor.tour-bookings.index'), false);
});

test('admin sidebar keeps both clusters', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.sidebar.hotel_management'), false)
        ->assertSee(__('admin.sidebar.tour_management'), false)
        ->assertSee(__('admin.sidebar.users'), false);
});
