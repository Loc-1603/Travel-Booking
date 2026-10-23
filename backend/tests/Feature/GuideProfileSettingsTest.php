<?php

use App\Models\Country;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Database\Seeders\TourHaGiangDemoSeeder;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'vendor', 'guide', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    // Strict split: tour flows belong to guides (vendors get 403 on guide-profile).
    $this->vendor = User::create([
        'name' => 'Price Vendor', 'email' => 'guide-price-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    // email_verified_at không nằm trong $fillable của User.
    $this->vendor->forceFill(['email_verified_at' => now()])->save();
    $this->vendor->assignRole('guide');

    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $this->province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang', 'slug' => 'ha-giang-price',
        'description' => 'Mountains', 'is_featured' => true,
    ]);

    $this->provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'province_id' => $this->province->id,
        'business_name' => 'Price Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $this->provider->id, 'province_id' => $this->province->id,
        'title' => 'Price Guide - Tour 1 ngày', 'base_price_daily' => 1000000,
        'status' => 'published',
    ]);
});

test('editing daily price in guide-profile settings updates the price shown to users', function (): void {
    $this->actingAs($this->vendor)
        ->put(route('admin.vendor.guide-profile.settings.update', $this->provider), [
            'province_id' => $this->province->id,
            'price_daily' => 2500000,
        ])
        ->assertSessionHas('success');

    // Tour đã tạo trước đó phải được đồng bộ (không chỉ tour tạo sau).
    expect((float) $this->tour->refresh()->base_price_daily)->toBe(2500000.0)
        ->and((float) $this->provider->refresh()->price_daily)->toBe(2500000.0);

    $res = $this->getJson('/api/v1/tour-providers?province_slug=ha-giang-price')->assertOk();
    expect((float) $res->json('data.data.0.price_from'))->toBe(2500000.0);

    $detail = $this->getJson('/api/v1/tour-providers/'.$this->provider->uuid)->assertOk();
    expect((float) $detail->json('data.price_from'))->toBe(2500000.0);
});

test('clearing the daily price keeps the tour prices untouched', function (): void {
    $this->actingAs($this->vendor)
        ->put(route('admin.vendor.guide-profile.settings.update', $this->provider), [
            'province_id' => $this->province->id,
        ])
        ->assertSessionHas('success');

    expect($this->provider->refresh()->price_daily)->toBeNull()
        ->and((float) $this->tour->refresh()->base_price_daily)->toBe(1000000.0);
});

test('backfill migration syncs stale tour prices with the guide daily price', function (): void {
    $this->provider->update(['price_daily' => 1750000]);
    // Giá tour vẫn còn cũ (lần tạo đầu tiên).
    expect((float) $this->tour->refresh()->base_price_daily)->toBe(1000000.0);

    (require database_path('migrations/2026_10_16_100000_sync_tour_base_price_daily_with_provider_price.php'))->up();

    expect((float) $this->tour->refresh()->base_price_daily)->toBe(1750000.0);
});

test('vendor rich bio edits show up on the public guide page', function (): void {
    $html = '<h2>Giới thiệu mới</h2><p>Nội dung chỉnh sửa từ trang vendor.</p>';

    $this->actingAs($this->vendor)
        ->put(route('admin.vendor.guide-profile.update', $this->provider), [
            'bio' => 'Nội dung chỉnh sửa từ trang vendor.',
            'bio_json' => json_encode(['type' => 'doc', 'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Giới thiệu mới']]],
            ]]),
            'bio_html' => $html,
        ])
        ->assertRedirect(route('admin.vendor.guide-profile.index'));

    $detail = $this->getJson('/api/v1/tour-providers/'.$this->provider->uuid)->assertOk();
    expect($detail->json('data.bio_html'))->toContain('<h2>Giới thiệu mới</h2>');
});

test('db:seed restores the default rich bio sample for Giàng A Páo', function (): void {
    TourProvince::create([
        'country_id' => $this->province->country_id, 'name' => 'Hà Giang', 'slug' => 'ha-giang',
        'description' => 'Mountains', 'is_featured' => true,
    ]);

    (new TourHaGiangDemoSeeder)->run();

    $provider = TourProvider::where('business_name', 'Giàng A Páo')->firstOrFail();
    expect($provider->bio_json)->toBeArray()
        ->and($provider->bio_json['type'])->toBe('doc')
        ->and($provider->bio_html)->toContain('<h2>Giàng A Páo — guide người Mông ở Đồng Văn</h2>')
        ->and(substr_count($provider->bio_html, '<img'))->toBe(4);

    // Vendor chỉnh giới thiệu trên web...
    $provider->update([
        'bio' => 'Đã chỉnh trên web',
        'bio_json' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        'bio_html' => '<p>Đã chỉnh trên web</p>',
    ]);

    // ...db:seed khôi phục lại nội dung mặc định.
    (new TourHaGiangDemoSeeder)->run();

    expect($provider->refresh()->bio)->toBe('Người Mông bản địa Đồng Văn, 8 năm chở khách loop Hà Giang bằng xe máy. Nhận chở tới: Đèo Mã Pí Lèng, Sông Nho Quế, Cột cờ Lũng Cú.')
        ->and($provider->bio_html)->toContain('<h2>Giàng A Páo — guide người Mông ở Đồng Văn</h2>')
        ->and(substr_count($provider->bio_html, '<img'))->toBe(4);
});
