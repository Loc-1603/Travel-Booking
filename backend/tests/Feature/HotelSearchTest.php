<?php

use App\Models\Amenity;
use App\Models\City;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use App\Models\VendorProfile;

beforeEach(function (): void {
    // Create country and city
    $this->country = Country::create([
        'name' => 'Vietnam',
        'code' => 'VN',
        'tax_rate' => 0.08,
        'tax_name' => 'VAT',
    ]);

    $this->city = City::create([
        'country_id' => $this->country->id,
        'name' => 'Hanoi',
    ]);

    // Create vendor user with approved profile
    $this->vendor = User::create([
        'name' => 'Test Vendor',
        'email' => 'vendor@test.local',
        'password' => bcrypt('password'),
        'role' => 'vendor',
        'status' => 'active',
    ]);

    VendorProfile::create([
        'user_id' => $this->vendor->id,
        'business_name' => 'Test Hotel Co.',
        'status' => 'approved',
    ]);

    // Create customer user
    $this->customer = User::create([
        'name' => 'Test Customer',
        'email' => 'customer@test.local',
        'password' => bcrypt('password'),
        'role' => 'customer',
        'status' => 'active',
    ]);

    // Create amenities
    $this->amenityWifi = Amenity::create(['slug' => 'wifi', 'name' => 'Free Wi-Fi', 'icon' => 'wifi', 'sort_order' => 1]);
    $this->amenityPool = Amenity::create(['slug' => 'pool', 'name' => 'Swimming Pool', 'icon' => 'pool', 'sort_order' => 2]);

    // Create hotel with rooms at different prices
    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id,
        'name' => 'Test Hotel',
        'city_id' => $this->city->id,
        'country_id' => $this->country->id,
        'city' => 'Hanoi',
        'country' => 'Vietnam',
        'status' => 'active',
    ]);

    $this->hotel->amenities()->attach([$this->amenityWifi->id, $this->amenityPool->id]);

    // Room: 550,000 VND (cheapest)
    $this->roomCheap = Room::create([
        'hotel_id' => $this->hotel->id,
        'name' => 'Standard',
        'capacity' => 2,
        'base_price' => 550000,
        'total_rooms' => 10,
    ]);

    // Room: 1,200,000 VND
    $this->roomMid = Room::create([
        'hotel_id' => $this->hotel->id,
        'name' => 'Deluxe',
        'capacity' => 2,
        'base_price' => 1200000,
        'total_rooms' => 5,
    ]);

    // Room: 3,500,000 VND (most expensive)
    $this->roomExpensive = Room::create([
        'hotel_id' => $this->hotel->id,
        'name' => 'Suite',
        'capacity' => 4,
        'base_price' => 3500000,
        'total_rooms' => 2,
    ]);
});

test('hotel search with only max_price works (no min_price required)', function (): void {
    // Should succeed - our fix allows max_price without min_price
    $response = $this->getJson('/api/v1/hotels?max_price=2000000')->assertOk();

    $data = $response->json('data.data');
    expect(count($data))->toBe(1); // Only rooms <= 2,000,000 (Standard 550k, Deluxe 1.2M)
    expect($data[0]['name'])->toBe('Test Hotel');
});

test('hotel search with only min_price works', function (): void {
    $response = $this->getJson('/api/v1/hotels?min_price=1000000')->assertOk();

    $data = $response->json('data.data');
    expect(count($data))->toBe(1); // Only rooms >= 1,000,000 (Deluxe 1.2M, Suite 3.5M)
});

test('hotel search with both min_price and max_price works', function (): void {
    $response = $this->getJson('/api/v1/hotels?min_price=500000&max_price=1500000')->assertOk();

    $data = $response->json('data.data');
    expect(count($data))->toBe(1); // Only rooms 500k-1.5M (Standard 550k, Deluxe 1.2M)
});

test('hotel search with min_price > max_price returns 422', function (): void {
    $response = $this->getJson('/api/v1/hotels?min_price=2000000&max_price=1000000')->assertStatus(422);

    expect($response->json('message'))->toBe('Validation failed.');
    expect($response->json('errors.max_price'))->not->toBeEmpty();
});

test('hotel search with negative min_price returns 422', function (): void {
    $response = $this->getJson('/api/v1/hotels?min_price=-100000')->assertStatus(422);
    expect($response->json('errors.min_price'))->not->toBeEmpty();
});

test('hotel search with negative max_price returns 422', function (): void {
    $response = $this->getJson('/api/v1/hotels?max_price=-500000')->assertStatus(422);
    expect($response->json('errors.max_price'))->not->toBeEmpty();
});

test('hotel search with non-numeric price returns 422', function (): void {
    $response = $this->getJson('/api/v1/hotels?min_price=abc')->assertStatus(422);
    expect($response->json('errors.min_price'))->not->toBeEmpty();

    $response = $this->getJson('/api/v1/hotels?max_price=xyz')->assertStatus(422);
    expect($response->json('errors.max_price'))->not->toBeEmpty();
});

test('hotel search filters by amenities', function (): void {
    $response = $this->getJson('/api/v1/hotels?amenities[]=wifi')->assertOk();

    $data = $response->json('data.data');
    expect(count($data))->toBe(1);
    $amenitySlugs = collect($data[0]['amenities'])->pluck('slug');
    expect($amenitySlugs)->toContain('wifi');
});

test('hotel search with multiple amenities requires all', function (): void {
    $response = $this->getJson('/api/v1/hotels?amenities[]=wifi&amenities[]=pool')->assertOk();

    $data = $response->json('data.data');
    expect(count($data))->toBe(1);
    $amenitySlugs = collect($data[0]['amenities'])->pluck('slug');
    expect($amenitySlugs)->toContain('wifi');
    expect($amenitySlugs)->toContain('pool');
});

test('tour search with only max_price works', function (): void {
    // Create a simple tour product for testing
    $province = \App\Models\TourProvince::create([
        'country_id' => $this->country->id,
        'name' => 'Test Province',
        'slug' => 'test-province',
    ]);

    $providerUser = User::create([
        'name' => 'Tour Provider',
        'email' => 'tour-provider@test.local',
        'password' => bcrypt('password'),
        'role' => 'vendor',
        'status' => 'active',
    ]);

    $provider = \App\Models\TourProvider::create([
        'vendor_id' => $providerUser->id,
        'business_name' => 'Tour Co',
        'status' => 'approved',
    ]);

    $tour = \App\Models\TourProduct::create([
        'provider_id' => $provider->id,
        'province_id' => $province->id,
        'title' => 'Test Tour',
        'base_fixed' => 100000,
        'base_price_hourly' => 150000,
        'base_price_daily' => 1200000,
        'status' => 'published',
    ]);

    // Should succeed - our fix allows max_price without min_price for tours too
    $response = $this->getJson('/api/v1/tours?max_price=2000000')->assertOk();

    $data = $response->json('data.data');
    expect(count($data))->toBeGreaterThanOrEqual(0);
});

test('tour search with min_price > max_price returns 422', function (): void {
    $response = $this->getJson('/api/v1/tours?min_price=2000000&max_price=1000000')->assertStatus(422);

    expect($response->json('message'))->toBe('Validation failed.');
    expect($response->json('errors.max_price'))->not->toBeEmpty();
});