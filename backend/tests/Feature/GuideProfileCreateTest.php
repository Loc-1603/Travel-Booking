<?php

use App\Models\TourProvider;
use App\Models\User;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'vendor', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    $this->vendor = User::factory()->create([
        'name' => 'Tour Vendor', 'email' => 'guide-create@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->vendor->assignRole('vendor');
});

test('a pending vendor can open the guide-profile create form', function () {
    $this->actingAs($this->vendor)
        ->get('/admin/vendor/guide-profile/create')
        ->assertOk()
        ->assertSee(__('admin.vendor.guide_profiles.create'), false);
});

test('a vendor can create their own guide profile without approval', function () {
    $this->actingAs($this->vendor)
        ->post('/admin/vendor/guide-profile', [
            'bio' => 'Local expert',
            'languages' => ['English', 'Korean'],
        ])
        ->assertRedirect(route('admin.vendor.guide-profile.index'));

    $provider = TourProvider::where('vendor_id', $this->vendor->id)->firstOrFail();
    expect($provider->business_name)->toBe('Tour Vendor')
        ->and($provider->status)->toBe('pending')
        ->and($provider->languages)->toBe(['English', 'Korean']);
});

test('business_name falls back to the vendor profile business name when set', function () {
    $this->vendor->vendorProfile()->create(['business_name' => 'Acme Tours']);

    $this->actingAs($this->vendor)
        ->post('/admin/vendor/guide-profile')
        ->assertRedirect(route('admin.vendor.guide-profile.index'));

    expect(TourProvider::where('vendor_id', $this->vendor->id)->firstOrFail()->business_name)->toBe('Acme Tours');
});

test('languages only accepts allowed foreign language names', function () {
    $this->actingAs($this->vendor)
        ->post('/admin/vendor/guide-profile', [
            'languages' => ['Vietnamese', 'English'],
        ])
        ->assertSessionHasErrors('languages.0');

    expect(TourProvider::where('vendor_id', $this->vendor->id)->count())->toBe(0);
});

test('rich bio is stored as an array without double-encoding', function () {
    $json = ['type' => 'doc', 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Chào bạn']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nội dung giới thiệu.']]],
    ]];

    $this->actingAs($this->vendor)
        ->post('/admin/vendor/guide-profile', [
            'bio' => 'Nội dung giới thiệu.',
            'bio_json' => json_encode($json),
            'bio_html' => '<h2>Chào bạn</h2><p>Nội dung giới thiệu.</p>',
        ])
        ->assertRedirect(route('admin.vendor.guide-profile.index'));

    $provider = TourProvider::where('vendor_id', $this->vendor->id)->firstOrFail();
    expect($provider->bio_json)->toBeArray()
        ->and($provider->bio_json['type'])->toBe('doc')
        ->and($provider->bio_html)->toContain('<h2>Chào bạn</h2>');
});
