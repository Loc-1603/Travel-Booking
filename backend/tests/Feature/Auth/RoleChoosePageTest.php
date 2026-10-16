<?php

use App\Enums\Role;
use App\Models\User;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'guide', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

test('guests are redirected from the root to the role chooser', function () {
    $this->get('/')->assertRedirect(route('login.choose', absolute: false));
});

test('the role chooser renders the three portal links', function () {
    $this->get('/login/choose')
        ->assertOk()
        ->assertSee('href="'.route('login.admin').'"', false)
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('href="'.route('login.guide').'"', false)
        ->assertSee(__('auth.choose_role.admin_name'), false)
        ->assertSee(__('auth.choose_role.vendor_name'), false)
        ->assertSee(__('auth.choose_role.guide_name'), false)
        ->assertSee(__('auth.choose_role.continue'), false);
});

test('authenticated users hitting the root are sent to their dashboard by role', function () {
    $admin = User::factory()->create(['role' => Role::ADMIN]);
    $admin->assignRole('admin');

    $vendor = User::factory()->create(['role' => Role::VENDOR]);
    $vendor->assignRole('vendor');

    $guide = User::factory()->create(['role' => Role::GUIDE]);
    $guide->assignRole('guide');

    $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($vendor)->get('/')->assertRedirect(route('admin.vendor.dashboard'));
    $this->actingAs($guide)->get('/')->assertRedirect(route('admin.vendor.dashboard'));
});
