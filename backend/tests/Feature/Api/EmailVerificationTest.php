<?php

use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'customer'] as $name) {
        Role::findOrCreate($name, 'web');
    }
});

test('customer registration does not create an account until the email is verified', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonMissingPath('data.token')
        ->assertJsonMissingPath('data.user');

    $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    $this->assertDatabaseHas('pending_registrations', ['email' => 'jane@example.com']);

    Notification::assertSentOnDemand(VerifyEmailNotification::class);
});

test('vendor registration does not create an account until the email is verified', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/register/vendor', [
        'name' => 'Acme Hotels',
        'email' => 'vendor@acme.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'business_name' => 'Acme Hotels Ltd',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'vendor@acme.test')
        ->assertJsonMissingPath('data.token');

    $this->assertDatabaseMissing('users', ['email' => 'vendor@acme.test']);

    $pending = PendingRegistration::where('email', 'vendor@acme.test')->firstOrFail();
    expect($pending->role)->toBe(\App\Enums\Role::VENDOR->value)
        ->and($pending->business_name)->toBe('Acme Hotels Ltd');

    Notification::assertSentOnDemand(VerifyEmailNotification::class);
});

test('unverified users cannot log in via the api', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('requires_verification', true);
});

test('verified users can log in via the api', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.email_verified', true)
        ->assertJsonStructure(['data' => ['token']]);
});

test('verifying a pending customer registration creates the account and redirects to the frontend', function () {
    Config::set('app.frontend_url', 'http://localhost:5173');

    $pending = PendingRegistration::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('password123'),
        'role' => 'customer',
        'expires_at' => now()->addHours(24),
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 'pending:'.$pending->uuid, 'hash' => sha1($pending->email)]
    );

    $response = $this->get($verificationUrl);

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    $this->assertDatabaseMissing('pending_registrations', ['email' => 'jane@example.com']);
    expect(User::where('email', 'jane@example.com')->first()->hasVerifiedEmail())->toBeTrue();

    $response->assertRedirect('http://localhost:5173/verify-email?verified=1');
});

test('verifying a pending vendor registration creates the vendor account and profile', function () {
    $pending = PendingRegistration::create([
        'name' => 'Acme Hotels',
        'email' => 'vendor@acme.test',
        'password' => bcrypt('password123'),
        'role' => 'vendor',
        'business_name' => 'Acme Hotels Ltd',
        'business_details' => 'Hanoi',
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

    $this->assertDatabaseMissing('pending_registrations', ['email' => 'vendor@acme.test']);
});

test('an invalid verification hash does not create the account', function () {
    $pending = PendingRegistration::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('password123'),
        'role' => 'customer',
        'expires_at' => now()->addHours(24),
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 'pending:'.$pending->uuid, 'hash' => sha1('wrong-email')]
    );

    $this->get($verificationUrl);

    $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    expect($pending->fresh())->not->toBeNull();
});

test('resend sends a new verification link for a pending registration', function () {
    Notification::fake();

    PendingRegistration::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('password123'),
        'role' => 'customer',
        'expires_at' => now()->addHours(24),
    ]);

    $this->postJson('/api/v1/email/verify-resend', ['email' => 'jane@example.com'])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentOnDemand(VerifyEmailNotification::class);
});

test('resend does not send to a verified or unknown email and still succeeds', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/email/verify-resend', ['email' => $user->email])->assertOk();
    $this->postJson('/api/v1/email/verify-resend', ['email' => 'nobody@example.com'])->assertOk();

    Notification::assertNothingSent();
});

test('resend works for an existing user waiting to verify a changed email', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->postJson('/api/v1/email/verify-resend', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});