<?php

use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

test('resending emails a fresh verification link for a pending registration', function () {
    Notification::fake();

    PendingRegistration::create([
        'name' => 'Acme Hotels',
        'email' => 'vendor@acme.test',
        'password' => bcrypt('password123'),
        'role' => 'vendor',
        'expires_at' => now()->addHours(24),
    ]);

    $this->post(route('register.verify.resend'), [
        'email' => 'vendor@acme.test',
        'portal' => 'vendor',
    ])
        ->assertRedirect(route('register.verify', ['portal' => 'vendor']))
        ->assertSessionHas('verify_email', 'vendor@acme.test')
        ->assertSessionHas('resent', true);

    Notification::assertSentOnDemand(
        VerifyEmailNotification::class,
        fn (VerifyEmailNotification $notification) => $notification->email === 'vendor@acme.test'
    );
});

test('resending keeps the guide portal in the redirect', function () {
    Notification::fake();

    PendingRegistration::create([
        'name' => 'Guide Demo',
        'email' => 'guide-resend@acme.test',
        'password' => bcrypt('password123'),
        'role' => 'guide',
        'expires_at' => now()->addHours(24),
    ]);

    $this->post(route('register.verify.resend'), [
        'email' => 'guide-resend@acme.test',
        'portal' => 'guide',
    ])->assertRedirect(route('register.verify', ['portal' => 'guide']));

    Notification::assertSentOnDemand(VerifyEmailNotification::class);
});

test('resending notifies an existing user waiting to verify a changed email', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->post(route('register.verify.resend'), ['email' => $user->email])
        ->assertRedirect(route('register.verify', ['portal' => 'vendor']))
        ->assertSessionHas('resent', true);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('resending to an unknown email still reports success without sending anything', function () {
    Notification::fake();

    $this->post(route('register.verify.resend'), ['email' => 'nobody@acme.test'])
        ->assertRedirect(route('register.verify', ['portal' => 'vendor']))
        ->assertSessionHas('resent', true);

    Notification::assertNothingSent();
});

test('an expired pending registration is not resent but the page still reports success', function () {
    Notification::fake();

    PendingRegistration::create([
        'name' => 'Acme Hotels',
        'email' => 'vendor@acme.test',
        'password' => bcrypt('password123'),
        'role' => 'vendor',
        'expires_at' => now()->subMinute(),
    ]);

    $this->post(route('register.verify.resend'), ['email' => 'vendor@acme.test'])
        ->assertRedirect(route('register.verify', ['portal' => 'vendor']))
        ->assertSessionHas('resent', true);

    Notification::assertNothingSent();
});

test('resend attempts are rate limited', function () {
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('register.verify.resend'), ['email' => 'nobody@acme.test'])
            ->assertRedirect(route('register.verify', ['portal' => 'vendor']));
    }

    $this->post(route('register.verify.resend'), ['email' => 'nobody@acme.test'])
        ->assertStatus(429);
});

test('the verify notice page shows the resent confirmation', function () {
    $this->withSession(['verify_email' => 'guide-resend@acme.test', 'resent' => true])
        ->get(route('register.verify', ['portal' => 'guide']))
        ->assertOk()
        ->assertSee(__('auth.register_verify.resent'), false);
});
