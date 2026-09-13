<?php

use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvince;
use App\Models\TourProvider;
use App\Models\TourReview;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Review Vendor', 'email' => 'review-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Review Customer', 'email' => 'review-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hội An', 'slug' => 'hoi-an-review',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Review Guide',
        'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Review tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $this->slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '09:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
});

test('review requires confirmed state and is one per booking', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $booking = TourBooking::where('uuid', $store->json('data.booking.uuid'))->first();

    // pending_payment -> 422
    $this->actingAs($this->customer)->postJson('/api/v1/tour-reviews', [
        'tour_booking_id' => $booking->id, 'rating' => 5, 'comment' => 'Great!',
    ])->assertStatus(422);

    // confirmed -> 201
    $booking->update(['status' => TourBookingStatus::CONFIRMED->value]);
    $this->actingAs($this->customer)->postJson('/api/v1/tour-reviews', [
        'tour_booking_id' => $booking->id, 'rating' => 5, 'comment' => 'Great guide!',
    ])->assertCreated();

    // second review -> 422 REVIEW_EXISTS
    $this->actingAs($this->customer)->postJson('/api/v1/tour-reviews', [
        'tour_booking_id' => $booking->id, 'rating' => 4,
    ])->assertStatus(422);

    // stranger booking id -> 404 (scoped to own customer_id)
    $stranger = User::create([
        'name' => 'Review Stranger', 'email' => 'review-stranger@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->actingAs($stranger)->postJson('/api/v1/tour-reviews', [
        'tour_booking_id' => $booking->id, 'rating' => 1,
    ])->assertNotFound();
});

test('public review list only shows approved and visible', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $booking = TourBooking::where('uuid', $store->json('data.booking.uuid'))->first();
    $booking->update(['status' => TourBookingStatus::COMPLETED->value]);

    $this->actingAs($this->customer)->postJson('/api/v1/tour-reviews', [
        'tour_booking_id' => $booking->id, 'rating' => 5, 'comment' => 'Pending moderation',
    ])->assertCreated();

    // Not approved yet -> public list empty
    $this->getJson('/api/v1/tour-reviews?tour_uuid='.$this->tour->uuid)
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);

    TourReview::where('tour_booking_id', $booking->id)->update(['approved' => true]);

    $this->getJson('/api/v1/tour-reviews?tour_uuid='.$this->tour->uuid)
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.rating', 5);

    // Hidden -> excluded again
    TourReview::where('tour_booking_id', $booking->id)->update(['hidden' => true]);
    $this->getJson('/api/v1/tour-reviews?tour_uuid='.$this->tour->uuid)
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);
});
