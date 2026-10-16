<?php

use App\Enums\Role;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'guide', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::findOrCreate($role, 'web');
    }
});

test('guide registration form omits the vendor business fields', function () {
    $this->get('/register/guide')
        ->assertOk()
        ->assertSee('name="name"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertDontSee('business_name', false)
        ->assertDontSee('business_details', false);
});

test('guide registration creates a pending registration with the guide role and no business fields', function () {
    Notification::fake();

    $this->post('/register/guide', [
        'name' => 'Guide Demo',
        'email' => 'guide-register@acme.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertRedirect(route('register.verify', ['portal' => 'guide'], absolute: false))
        ->assertSessionHas('verify_email', 'guide-register@acme.test');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'guide-register@acme.test']);

    $pending = PendingRegistration::where('email', 'guide-register@acme.test')->firstOrFail();
    expect($pending->role)->toBe('guide')
        ->and($pending->business_name)->toBeNull()
        ->and($pending->business_details)->toBeNull();

    Notification::assertSentOnDemand(VerifyEmailNotification::class);
});

test('verifying a pending guide registration creates a guide account without a vendor profile', function () {
    $pending = PendingRegistration::create([
        'name' => 'Guide Demo',
        'email' => 'guide-register@acme.test',
        'password' => bcrypt('password123'),
        'role' => 'guide',
        'expires_at' => now()->addHours(24),
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 'pending:'.$pending->uuid, 'hash' => sha1($pending->email)]
    );

    $this->get($verificationUrl)
        ->assertRedirect(route('register.verify', ['portal' => 'guide', 'verified' => 1]))
        ->assertSessionHas('verify_email', 'guide-register@acme.test');

    $user = User::where('email', 'guide-register@acme.test')->firstOrFail();
    expect($user->role)->toBe(Role::GUIDE)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->hasRole('guide'))->toBeTrue()
        ->and($user->vendorProfile()->exists())->toBeFalse();
});

test('the verify notice page dims the continue button until the email is verified', function () {
    $this->withSession(['verify_email' => 'guide-register@acme.test'])
        ->get(route('register.verify', ['portal' => 'guide']))
        ->assertOk()
        ->assertSee(__('auth.register_verify.title'), false)
        ->assertSee(__('auth.register_verify.sent_to', ['email' => 'guide-register@acme.test']), false)
        ->assertSee('aria-disabled="true"', false)
        ->assertSee('id="resend-verification"', false)
        ->assertSee('data-countdown="15"', false)
        ->assertSee(__('auth.register_verify.resend_wait', ['seconds' => 15]), false)
        ->assertDontSee(__('auth.register_verify.verified_title'), false);
});

test('the verify notice page enables the continue button after verification', function () {
    $this->get(route('register.verify', ['portal' => 'guide', 'verified' => 1]))
        ->assertOk()
        ->assertSee(__('auth.register_verify.verified_title'), false)
        ->assertDontSee('aria-disabled="true"', false)
        ->assertDontSee('id="resend-verification"', false)
        ->assertSee('href="'.route('login.guide').'"', false);
});

test('verification rolls back cleanly when the guide role is not seeded yet', function () {
    // Risk #4: assignRole('guide') throws RoleDoesNotExist before the seeder
    // is run. The transaction must leave no half-created user behind, so the
    // same verification link still works after seeding.
    \Spatie\Permission\Models\Role::findByName('guide', 'web')->delete();

    $pending = PendingRegistration::create([
        'name' => 'Guide Demo',
        'email' => 'guide-register@acme.test',
        'password' => bcrypt('password123'),
        'role' => 'guide',
        'expires_at' => now()->addHours(24),
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 'pending:'.$pending->uuid, 'hash' => sha1($pending->email)]
    );

    $this->get($verificationUrl)->assertStatus(500);

    $this->assertDatabaseMissing('users', ['email' => 'guide-register@acme.test']);
    $this->assertDatabaseHas('pending_registrations', ['email' => 'guide-register@acme.test']);
});
