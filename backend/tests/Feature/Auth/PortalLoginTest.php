<?php

use App\Enums\Role;
use App\Models\User;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'guide', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

test('admin portal login hides the registration links', function () {
    $this->get('/login/admin')
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee(route('register.vendor'), false)
        ->assertDontSee(route('register.guide'), false);
});

test('vendor portal login links to vendor registration only', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee(route('register.vendor'), false)
        ->assertDontSee(route('register.guide'), false);
});

test('guide portal login links to guide registration only', function () {
    $this->get('/login/guide')
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee(route('register.guide'), false)
        ->assertDontSee(route('register.vendor'), false);
});

test('each portal authenticates its own role and redirects to the matching dashboard', function () {
    $admin = User::factory()->create(['role' => Role::ADMIN]);
    $admin->assignRole('admin');
    $vendor = User::factory()->create(['role' => Role::VENDOR]);
    $vendor->assignRole('vendor');
    $guide = User::factory()->create(['role' => Role::GUIDE]);
    $guide->assignRole('guide');

    $this->post('/login/admin', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);

    auth()->logout();

    $this->post('/login', ['email' => $vendor->email, 'password' => 'password'])
        ->assertRedirect(route('admin.vendor.dashboard'));
    $this->assertAuthenticatedAs($vendor);

    auth()->logout();

    $this->post('/login/guide', ['email' => $guide->email, 'password' => 'password'])
        ->assertRedirect(route('admin.vendor.dashboard'));
    $this->assertAuthenticatedAs($guide);
});

test('an admin account cannot sign in through the guide portal', function () {
    $admin = User::factory()->create(['role' => Role::ADMIN]);
    $admin->assignRole('admin');

    $this->from('/login/guide')
        ->post('/login/guide', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect('/login/guide')
        ->assertSessionHasErrors([
            'email' => __('auth.login.wrong_portal', [
                'portal' => 'guide',
                'url' => route('login.admin'),
            ]),
        ]);

    $this->assertGuest();
});

test('customers cannot sign in through the admin or guide portals', function () {
    $customer = User::factory()->create(['role' => Role::CUSTOMER]);
    $customer->assignRole('customer');

    foreach (['admin', 'guide'] as $portal) {
        $this->post('/login/'.$portal, ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
});
