<?php

use App\Enums\Role;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;

beforeEach(function (): void {
    foreach (['vendor', 'guide'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $this->province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang', 'slug' => 'ha-giang-split',
        'description' => 'Mountains', 'is_featured' => true,
    ]);

    $this->tourOnly = User::factory()->create([
        'name' => 'Tour Only', 'email' => 'tour-only@test.local',
        'password' => 'password', 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->tourOnly->assignRole('vendor');
    $this->tourOnlyProvider = TourProvider::create([
        'vendor_id' => $this->tourOnly->id, 'province_id' => $this->province->id,
        'business_name' => 'Tour Only Guide', 'status' => 'approved',
    ]);

    $this->both = User::factory()->create([
        'name' => 'Both Vendor', 'email' => 'both-vendor@test.local',
        'password' => 'password', 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->both->assignRole('vendor');
    Hotel::create([
        'vendor_id' => $this->both->id, 'name' => 'Both Hotel',
        'city' => 'Hà Nội', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->bothProvider = TourProvider::create([
        'vendor_id' => $this->both->id, 'province_id' => $this->province->id,
        'business_name' => 'Both Tours', 'status' => 'approved',
    ]);

    $this->hotelOnly = User::factory()->create([
        'name' => 'Hotel Only', 'email' => 'hotel-only@test.local',
        'password' => 'password', 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->hotelOnly->assignRole('vendor');
    Hotel::create([
        'vendor_id' => $this->hotelOnly->id, 'name' => 'Hotel Only Stay',
        'city' => 'Đà Nẵng', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
});

test('dry run reports the plan without writing to the database', function (): void {
    $this->artisan('staff:split-roles', ['--dry-run' => true])
        ->expectsOutputToContain('keep vendor')
        ->expectsOutputToContain('promote to guide')
        ->expectsOutputToContain('companion guide')
        ->assertSuccessful();

    expect($this->tourOnly->refresh()->role)->toBe(Role::VENDOR)
        ->and($this->both->refresh()->role)->toBe(Role::VENDOR)
        ->and($this->bothProvider->refresh()->vendor_id)->toBe($this->both->id)
        ->and(User::where('email', 'both-vendor+guide@test.local')->exists())->toBeFalse()
        ->and(User::where('role', Role::GUIDE->value)->count())->toBe(0);
});

test('tour-only vendor is promoted to guide and keeps the provider', function (): void {
    $this->artisan('staff:split-roles')->assertSuccessful();

    $this->tourOnly->refresh();
    expect($this->tourOnly->role)->toBe(Role::GUIDE)
        ->and($this->tourOnly->hasRole('guide'))->toBeTrue()
        ->and($this->tourOnly->hasRole('vendor'))->toBeFalse()
        ->and($this->tourOnlyProvider->refresh()->vendor_id)->toBe($this->tourOnly->id);
});

test('hotel+tour vendor keeps hotels and gets a companion guide with copied password', function (): void {
    $this->artisan('staff:split-roles')->assertSuccessful();

    $this->both->refresh();
    expect($this->both->role)->toBe(Role::VENDOR)
        ->and($this->both->hasRole('vendor'))->toBeTrue()
        ->and(Hotel::where('vendor_id', $this->both->id)->count())->toBe(1);

    $companion = User::where('email', 'both-vendor+guide@test.local')->first();
    expect($companion)->not->toBeNull()
        ->and($companion->role)->toBe(Role::GUIDE)
        ->and($companion->name)->toBe('Both Vendor')
        ->and($companion->status)->toBe('active')
        ->and($companion->email_verified_at)->not->toBeNull()
        ->and($companion->hasRole('guide'))->toBeTrue()
        ->and($companion->hasRole('vendor'))->toBeFalse()
        ->and($companion->getRawOriginal('password'))->toBe($this->both->getRawOriginal('password'))
        ->and($this->bothProvider->refresh()->vendor_id)->toBe($companion->id);
});

test('hotel-only vendor is left untouched', function (): void {
    $this->artisan('staff:split-roles')->assertSuccessful();

    $this->hotelOnly->refresh();
    expect($this->hotelOnly->role)->toBe(Role::VENDOR)
        ->and($this->hotelOnly->hasRole('vendor'))->toBeTrue()
        ->and($this->hotelOnly->hasRole('guide'))->toBeFalse();
});

test('promoted and companion guides can log in via the guide portal', function (): void {
    $this->artisan('staff:split-roles')->assertSuccessful();

    $this->post('/login/guide', ['email' => 'tour-only@test.local', 'password' => 'password'])
        ->assertRedirect(route('admin.vendor.dashboard'));
    expect(auth()->user()->email)->toBe('tour-only@test.local');

    auth()->logout();

    $this->post('/login/guide', ['email' => 'both-vendor+guide@test.local', 'password' => 'password'])
        ->assertRedirect(route('admin.vendor.dashboard'));
    expect(auth()->user()->email)->toBe('both-vendor+guide@test.local');
});
