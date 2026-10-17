<?php

use App\Models\Booking;
use App\Models\City;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Review;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;

beforeEach(function (): void {
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $city = City::create(['country_id' => $country->id, 'name' => 'Hanoi']);

    $this->vendor = User::create([
        'name' => 'Hotel Review Vendor', 'email' => 'hotel-review-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    VendorProfile::create([
        'user_id' => $this->vendor->id, 'business_name' => 'Review Hotel Co.', 'status' => 'approved',
    ]);

    $this->customer = User::create([
        'name' => 'Hotel Review Customer', 'email' => 'hotel-review-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);

    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Review Hotel',
        'city_id' => $city->id, 'country_id' => $country->id,
        'city' => 'Hanoi', 'country' => 'Vietnam', 'status' => 'active',
    ]);

    $this->booking = Booking::create([
        'customer_id' => $this->customer->id,
        'hotel_id' => $this->hotel->id,
        'status' => 'completed',
        'check_in' => Carbon::yesterday()->toDateString(),
        'check_out' => Carbon::today()->toDateString(),
        'total_price' => 1000000,
        'currency' => 'VND',
    ]);
});

test('hotel review is published immediately without approval and counted in stats', function (): void {
    $res = $this->actingAs($this->customer)->postJson('/api/v1/reviews', [
        'booking_id' => $this->booking->id, 'rating' => 5, 'comment' => 'Great stay!',
    ])->assertCreated();

    expect($res->json('data.rating'))->toBe(5)
        ->and($res->json('data.booking_id'))->toBe($this->booking->id);

    // Hiển thị ngay ở danh sách công khai.
    $list = $this->getJson('/api/v1/reviews?hotel_id='.$this->hotel->id)->assertOk();
    expect($list->json('data.meta.total'))->toBe(1)
        ->and($list->json('data.data.0.comment'))->toBe('Great stay!')
        ->and($list->json('data.data.0.customer_name'))->toBe('Hotel Review Customer');

    // Rating/count của hotel tính ngay, không cần duyệt.
    $hotel = $this->getJson('/api/v1/hotels/'.$this->hotel->id)->assertOk();
    expect((int) $hotel->json('data.review_count'))->toBe(1)
        ->and((float) $hotel->json('data.average_rating'))->toBe(5.0);

    // Filter min_rating cũng tính review chưa từng duyệt.
    $search = $this->getJson('/api/v1/hotels?min_rating=5')->assertOk();
    expect($search->json('data.meta.total'))->toBe(1);
});

test('hotel review requires eligible booking state and is one per booking', function (): void {
    $pending = Booking::create([
        'customer_id' => $this->customer->id,
        'hotel_id' => $this->hotel->id,
        'status' => 'pending_payment',
        'check_in' => Carbon::tomorrow()->toDateString(),
        'check_out' => Carbon::tomorrow()->addDay()->toDateString(),
        'total_price' => 500000,
        'currency' => 'VND',
    ]);

    $this->actingAs($this->customer)->postJson('/api/v1/reviews', [
        'booking_id' => $pending->id, 'rating' => 4,
    ])->assertStatus(422);

    $this->actingAs($this->customer)->postJson('/api/v1/reviews', [
        'booking_id' => $this->booking->id, 'rating' => 5,
    ])->assertCreated();

    $this->actingAs($this->customer)->postJson('/api/v1/reviews', [
        'booking_id' => $this->booking->id, 'rating' => 3,
    ])->assertStatus(422);

    $stranger = User::create([
        'name' => 'Hotel Stranger', 'email' => 'hotel-stranger@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->actingAs($stranger)->postJson('/api/v1/reviews', [
        'booking_id' => $this->booking->id, 'rating' => 1,
    ])->assertNotFound();
});

test('hidden hotel reviews are excluded from list and stats', function (): void {
    $this->actingAs($this->customer)->postJson('/api/v1/reviews', [
        'booking_id' => $this->booking->id, 'rating' => 2, 'comment' => 'Spam',
    ])->assertCreated();

    Review::where('booking_id', $this->booking->id)->update(['hidden' => true]);

    $this->getJson('/api/v1/reviews?hotel_id='.$this->hotel->id)
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);

    $hotel = $this->getJson('/api/v1/hotels/'.$this->hotel->id)->assertOk();
    expect((int) $hotel->json('data.review_count'))->toBe(0);
});

test('hotel review accepts images', function (): void {
    \Illuminate\Support\Facades\Storage::fake('public');

    $res = $this->actingAs($this->customer)->post('/api/v1/reviews', [
        'booking_id' => $this->booking->id, 'rating' => 5, 'comment' => 'Ảnh đẹp',
        'images' => [
            \Illuminate\Http\UploadedFile::fake()->image('room.jpg'),
        ],
    ])->assertCreated();

    expect($res->json('data.images'))->toHaveCount(1)
        ->and($res->json('data.images.0.url'))->toContain('review-images');
});
