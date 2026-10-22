<?php

use App\Models\User;

it('renders the public auth pages', function () {
    $this->get(route('login.choose'))
        ->assertOk()
        ->assertSee(__('auth.choose_role.title'));

    $this->get(route('login'))->assertOk();
    $this->get(route('login.admin'))->assertOk();
    $this->get(route('login.guide'))->assertOk();
    $this->get(route('register.vendor'))->assertOk();
    $this->get(route('register.guide'))->assertOk();
    $this->get(route('password.request'))->assertOk();
    $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'test@example.com']))->assertOk();
});

it('renders the authenticated auth pages', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('password.confirm'))->assertOk();
    $this->actingAs($user)->get(route('verification.notice'))->assertOk();
});
