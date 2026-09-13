<?php

use App\Models\Country;
use App\Models\TourAttraction;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\TourReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->customer = User::create([
        'name' => 'Guide Search Customer', 'email' => 'guide-search-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $vendorA = User::create([
        'name' => 'Guide A Vendor', 'email' => 'guide-a-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $vendorB = User::create([
        'name' => 'Guide B Vendor', 'email' => 'guide-b-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);

    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $this->province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang', 'slug' => 'ha-giang-guides',
        'description' => 'Mountains', 'is_featured' => true,
    ]);

    $this->providerA = TourProvider::create([
        'vendor_id' => $vendorA->id, 'business_name' => 'Local Expert A',
        'bio' => 'Local expert', 'languages' => ['vi', 'en'], 'status' => 'approved',
    ]);
    $this->providerB = TourProvider::create([
        'vendor_id' => $vendorB->id, 'business_name' => 'Local Expert B',
        'status' => 'approved',
    ]);

    $this->tourA = TourProduct::create([
        'provider_id' => $this->providerA->id, 'province_id' => $this->province->id,
        'title' => 'A Loop Tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'status' => 'published',
    ]);
    $this->tourB = TourProduct::create([
        'provider_id' => $this->providerB->id, 'province_id' => $this->province->id,
        'title' => 'B Easy Tour', 'base_fixed' => 100000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);

    // Guide A free 3 days, Guide B free only day 1.
    $this->day1 = Carbon::tomorrow()->toDateString();
    $this->day2 = Carbon::tomorrow()->addDay()->toDateString();
    $this->day3 = Carbon::tomorrow()->addDays(2)->toDateString();
    foreach ([$this->day1, $this->day2, $this->day3] as $date) {
        TourAvailabilitySlot::create([
            'tour_id' => $this->tourA->id, 'date' => $date,
            'start_time' => '08:00:00', 'end_time' => '17:00:00', 'status' => 'available',
        ]);
    }
    $this->slotB = TourAvailabilitySlot::create([
        'tour_id' => $this->tourB->id, 'date' => $this->day1,
        'start_time' => '08:00:00', 'end_time' => '17:00:00', 'status' => 'available',
    ]);

    // Reviews: A = 5 + 4 (avg 4.5, count 2, score 9), B = 5 (avg 5, count 1, score 5).
    $seed = function ($provider, $tour, array $dates, array $ratings): void {
        foreach ($ratings as $i => $rating) {
            $slot = TourAvailabilitySlot::create([
                'tour_id' => $tour->id, 'date' => $dates[$i],
                'start_time' => '18:00:00', 'end_time' => '19:00:00', 'status' => 'booked',
            ]);
            $booking = TourBooking::create([
                'customer_id' => $this->customer->id, 'tour_id' => $tour->id,
                'slot_id' => $slot->id, 'provider_id' => $provider->id,
                'province_id' => $this->province->id,
                'start_at' => $dates[$i].' 18:00:00', 'end_at' => $dates[$i].' 19:00:00',
                'total_price' => 100000, 'status' => 'completed',
            ]);
            TourReview::create([
                'tour_booking_id' => $booking->id, 'rating' => $rating,
                'comment' => 'ok', 'approved' => true, 'hidden' => false,
            ]);
        }
    };
    $seed($this->providerA, $this->tourA, [$this->day1, $this->day2], [5, 4]);
    $seed($this->providerB, $this->tourB, [$this->day1], [5]);
});

test('guide list ranks by score and exposes rating fields', function (): void {
    $res = $this->getJson('/api/v1/tour-providers?province_slug=ha-giang-guides')->assertOk();

    $data = $res->json('data.data');
    expect(count($data))->toBe(2)
        // Score desc: A (4.5*2=9) before B (5*1=5)
        ->and($data[0]['business_name'])->toBe('Local Expert A')
        ->and((float) $data[0]['average_rating'])->toBe(4.5)
        ->and((int) $data[0]['review_count'])->toBe(2)
        ->and((float) $data[0]['score'])->toBe(9.0)
        ->and((int) $data[0]['tours_count'])->toBe(1)
        ->and((float) $data[0]['price_from'])->toBe(450000.0)
        ->and($data[0]['primary_tour']['uuid'])->toBe($this->tourA->uuid)
        ->and($data[1]['business_name'])->toBe('Local Expert B');
});

test('guide list with unknown province returns empty', function (): void {
    $this->getJson('/api/v1/tour-providers?province_slug=nowhere')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);
});

test('guide list filters vendors free for every selected day', function (): void {
    // Single day: both free.
    $one = $this->getJson("/api/v1/tour-providers?province_slug=ha-giang-guides&from={$this->day1}&to={$this->day1}")
        ->assertOk();
    expect($one->json('data.meta.total'))->toBe(2);

    // Two days: only Guide A free on both.
    $two = $this->getJson("/api/v1/tour-providers?province_slug=ha-giang-guides&from={$this->day1}&to={$this->day2}")
        ->assertOk();
    expect($two->json('data.meta.total'))->toBe(1)
        ->and($two->json('data.data.0.business_name'))->toBe('Local Expert A');
});

test('guide list date filter requires province', function (): void {
    $this->getJson("/api/v1/tour-providers?from={$this->day1}&to={$this->day2}")
        ->assertStatus(422);
});

test('guide profile shows bio tours and rating', function (): void {
    $res = $this->getJson('/api/v1/tour-providers/'.$this->providerA->uuid.'?province_slug=ha-giang-guides')
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Local Expert A')
        ->assertJsonPath('data.bio', 'Local expert')
        ->assertJsonPath('data.tours.0.title', 'A Loop Tour');

    // Numeric fields compared as floats (JSON may encode 9.0 as 9).
    $data = $res->json('data');
    expect((float) $data['average_rating'])->toBe(4.5)
        ->and((int) $data['review_count'])->toBe(2)
        ->and((float) $data['score'])->toBe(9.0);
});

test('guide profile unknown uuid returns 404', function (): void {
    $this->getJson('/api/v1/tour-providers/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

test('review list filters by provider uuid with booker context', function (): void {
    $resA = $this->getJson('/api/v1/tour-reviews?provider_uuid='.$this->providerA->uuid)->assertOk();
    expect($resA->json('data.meta.total'))->toBe(2)
        ->and($resA->json('data.data.0.customer_name'))->toBe('Guide Search Customer')
        ->and($resA->json('data.data.0.tour.title'))->toBe('A Loop Tour');

    $resB = $this->getJson('/api/v1/tour-reviews?provider_uuid='.$this->providerB->uuid)->assertOk();
    expect($resB->json('data.meta.total'))->toBe(1)
        ->and($resB->json('data.data.0.tour.title'))->toBe('B Easy Tour');

    // Unknown vendor uuid -> empty, consistent with tour_uuid behaviour.
    $this->getJson('/api/v1/tour-reviews?provider_uuid=00000000-0000-0000-0000-000000000000')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);
});

test('guide avatar falls back to vendor account avatar and keeps external urls', function (): void {
    Storage::fake('public');
    $vendorA = User::where('email', 'guide-a-vendor@test.local')->firstOrFail();
    Storage::disk('public')->put('avatars/vendor-a.jpg', 'fake-image');
    $vendorA->update(['avatar' => 'avatars/vendor-a.jpg']);

    // No provider avatar -> vendor account avatar.
    $res = $this->getJson('/api/v1/tour-providers/'.$this->providerA->uuid)->assertOk();
    expect($res->json('data.avatar'))->toBe(Storage::disk('public')->url('avatars/vendor-a.jpg'));

    // External URL passes through untouched.
    $this->providerA->update(['avatar' => 'https://example.com/guide-a.jpg']);
    $resExternal = $this->getJson('/api/v1/tour-providers/'.$this->providerA->uuid)->assertOk();
    expect($resExternal->json('data.avatar'))->toBe('https://example.com/guide-a.jpg');

    // Neither provider nor vendor avatar -> null (frontend shows initial fallback).
    $resB = $this->getJson('/api/v1/tour-providers/'.$this->providerB->uuid)->assertOk();
    expect($resB->json('data.avatar'))->toBeNull();

    // Tour detail nests the same resolved provider avatar.
    $tourRes = $this->getJson('/api/v1/tours/'.$this->tourA->uuid)->assertOk();
    expect($tourRes->json('data.provider.avatar'))->toBe('https://example.com/guide-a.jpg');
});

test('province detail exposes external attraction images', function (): void {
    TourAttraction::create([
        'province_id' => $this->province->id,
        'name' => 'Test Waterfall',
        'description' => 'Nice view',
        'image' => 'https://upload.wikimedia.org/wikipedia/commons/f/f3/Example.jpg',
        'is_famous' => true,
    ]);

    $res = $this->getJson('/api/v1/tour-provinces/'.$this->province->slug)->assertOk();
    $attractions = $res->json('data.attractions');
    expect($attractions)->not->toBeEmpty();

    $found = collect($attractions)->firstWhere('name', 'Test Waterfall');
    expect($found)->not->toBeNull()
        ->and($found['image'])->toBe('https://upload.wikimedia.org/wikipedia/commons/f/f3/Example.jpg')
        ->and($found['is_famous'])->toBeTrue();
});

test('tour detail includes province attractions with images', function (): void {
    TourAttraction::create([
        'province_id' => $this->province->id,
        'name' => 'Detail Falls',
        'description' => 'Seen on tour page',
        'image' => 'https://upload.wikimedia.org/wikipedia/commons/f/f3/Detail.jpg',
        'is_famous' => false,
    ]);

    $res = $this->getJson('/api/v1/tours/'.$this->tourA->uuid)->assertOk();
    $attractions = $res->json('data.province.attractions');
    expect($attractions)->not->toBeEmpty();

    $found = collect($attractions)->firstWhere('name', 'Detail Falls');
    expect($found)->not->toBeNull()
        ->and($found['image'])->toBe('https://upload.wikimedia.org/wikipedia/commons/f/f3/Detail.jpg');
});

test('seeded guide prefix is stripped from provider names', function (): void {
    $vendor = User::where('email', 'guide-b-vendor@test.local')->firstOrFail();
    $provider = TourProvider::create([
        'vendor_id' => $vendor->id, 'business_name' => 'Guide Temp Name', 'status' => 'approved',
    ]);

    (require database_path('migrations/2026_09_12_100200_strip_guide_prefix_from_provider_names.php'))->up();

    expect($provider->refresh()->business_name)->toBe('Temp Name')
        // Untouched names keep working.
        ->and($this->providerA->refresh()->business_name)->toBe('Local Expert A');
});
