<?php

use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'customer'] as $name) {
        Role::findOrCreate($name, 'web');
    }
});

test('vendor registration from the blade form does not create an account until verified', function () {
    Notification::fake();

    $response = $this->post('/register/vendor', [
        'name' => 'Acme Hotels',
        'email' => 'vendor@acme.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'business_name' => 'Acme Hotels Ltd',
    ]);

    $response->assertRedirect(route('login', absolute: false));
    $this->assertGuest();

    $this->assertDatabaseMissing('users', ['email' => 'vendor@acme.test']);
    $this->assertDatabaseHas('pending_registrations', ['email' => 'vendor@acme.test']);

    Notification::assertSentOnDemand(VerifyEmailNotification::class);
});

test('verifying a pending vendor registration creates the vendor account', function () {
    $pending = PendingRegistration::create([
        'name' => 'Acme Hotels',
        'email' => 'vendor@acme.test',
        'password' => bcrypt('password123'),
        'role' => 'vendor',
        'business_name' => 'Acme Hotels Ltd',
        'expires_at' => now()->addHours(24),
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 'pending:'.$pending->uuid, 'hash' => sha1($pending->email)]
    );

    $this->get($verificationUrl);

    $user = User::where('email', 'vendor@acme.test')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->role)->toBe(\App\Enums\Role::VENDOR)
        ->and($user->vendorProfile()->exists())->toBeTrue();
});

test('unverified vendors cannot access the vendor dashboard', function () {
    $user = User::factory()->unverified()->create(['role' => \App\Enums\Role::VENDOR]);
    $user->assignRole('vendor');

    $response = $this->actingAs($user)->get(route('admin.vendor.dashboard'));

    $response->assertRedirect(route('verification.notice', absolute: false));
});

test('verified vendors can access the vendor dashboard', function () {
    $user = User::factory()->create(['role' => \App\Enums\Role::VENDOR]);
    $user->assignRole('vendor');
    $user->vendorProfile()->create(['status' => \App\Models\VendorProfile::STATUS_PENDING]);

    $this->actingAs($user)->get(route('admin.vendor.dashboard'))->assertOk();
});