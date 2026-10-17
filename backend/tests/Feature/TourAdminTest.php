<?php

use App\Enums\Role;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    foreach (['super-admin', 'admin', 'vendor', 'guide', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    $this->superAdmin = User::create([
        'name' => 'Super Admin', 'email' => 'tour-admin@test.local',
        'password' => bcrypt('password'), 'role' => Role::SUPER_ADMIN->value, 'status' => 'active',
    ]);
    $this->superAdmin->assignRole('super-admin');
    $this->guide = User::factory()->create([
        'email' => 'tour-guide-admin@test.local',
        'role' => Role::GUIDE, 'status' => 'active',
    ]);
    $this->guide->assignRole('guide');
    $this->customer = User::factory()->create([
        'email' => 'tour-customer-admin@test.local',
        'role' => Role::CUSTOMER, 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $this->countryId = $country->id;
    $this->province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Đà Lạt', 'slug' => 'da-lat-admin',
        'description' => 'Test', 'is_featured' => false,
    ]);
    $this->provider = TourProvider::create([
        'vendor_id' => $this->guide->id, 'business_name' => 'Admin Guide',
        'status' => 'pending',
    ]);

    $this->createBooking = function (TourProvider $provider, string $title = 'Guide Admin Tour'): TourBooking {
        $tour = TourProduct::create([
            'provider_id' => $provider->id, 'province_id' => $this->province->id,
            'title' => $title, 'base_fixed' => 200000,
            'base_price_hourly' => 100000, 'base_price_daily' => 800000,
            'status' => 'published',
        ]);
        $date = Carbon::tomorrow()->toDateString();
        $slot = TourAvailabilitySlot::create([
            'tour_id' => $tour->id, 'date' => $date,
            'start_time' => '09:00:00', 'end_time' => '12:00:00', 'status' => 'booked',
        ]);
        $booking = TourBooking::create([
            'customer_id' => $this->customer->id, 'tour_id' => $tour->id, 'slot_id' => $slot->id,
            'provider_id' => $provider->id, 'province_id' => $this->province->id,
            'start_at' => $date.' 09:00:00', 'end_at' => $date.' 12:00:00',
            'total_price' => 300000, 'status' => 'confirmed',
        ]);
        $booking->messages()->create(['sender_id' => $this->customer->id, 'body' => 'Xin chào guide!']);

        return $booking;
    };
});

test('super admin manages tour provinces and providers', function (): void {
    $this->actingAs($this->superAdmin)->get('/admin/tour-provinces')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/tour-provinces/create')->assertOk();

    $this->actingAs($this->superAdmin)->post('/admin/tour-provinces', [
        'country_id' => $this->countryId, 'name' => 'Nha Trang', 'description' => 'Beach tours',
    ])->assertRedirect('/admin/tour-provinces');
    expect(TourProvince::where('name', 'Nha Trang')->exists())->toBeTrue();

    $this->actingAs($this->superAdmin)->get('/admin/tour-providers')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/tour-providers/'.$this->provider->id)->assertOk();

    $this->actingAs($this->superAdmin)
        ->post('/admin/tour-providers/'.$this->provider->id.'/approve')
        ->assertRedirect();
    expect($this->provider->fresh()->status)->toBe('approved');
});

test('super admin sees tour disputes and reviews queues', function (): void {
    $this->actingAs($this->superAdmin)->get('/admin/tour-disputes')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/reviews')->assertOk();
    $this->actingAs($this->superAdmin)->get('/admin/tour-attractions')->assertOk();
});

test('admin moderation lists hotel and tour reviews together and toggles hidden', function (): void {
    $booking = ($this->createBooking)($this->provider);
    $review = \App\Models\Review::create([
        'tour_booking_id' => $booking->id, 'rating' => 4, 'comment' => 'Unified queue',
    ]);

    $this->actingAs($this->superAdmin)->get('/admin/reviews')
        ->assertOk()
        ->assertSee('Unified queue');

    $this->actingAs($this->superAdmin)->get('/admin/reviews/'.$review->id)->assertOk();

    $this->actingAs($this->superAdmin)->patch('/admin/reviews/'.$review->id, ['action' => 'hide'])
        ->assertRedirect('/admin/reviews/'.$review->id);
    expect($review->fresh()->hidden)->toBeTrue();

    $this->actingAs($this->superAdmin)->patch('/admin/reviews/'.$review->id, ['action' => 'unhide'])
        ->assertRedirect('/admin/reviews/'.$review->id);
    expect($review->fresh()->hidden)->toBeFalse();

    // Route kiểm duyệt tour riêng cũ đã bị xoá.
    $this->actingAs($this->superAdmin)->get('/admin/tour-reviews')->assertNotFound();
});

test('guide manages own tour bookings inbox and messages', function (): void {
    $booking = ($this->createBooking)($this->provider);

    $this->actingAs($this->guide)->get('/admin/vendor/tour-bookings')
        ->assertOk()
        ->assertSee('Guide Admin Tour');

    $this->actingAs($this->guide)->get('/admin/vendor/tour-bookings/'.$booking->uuid.'/invoice')
        ->assertOk();

    $this->actingAs($this->guide)->get('/admin/vendor/tour-messages')
        ->assertOk()
        ->assertSee('Guide Admin Tour');

    $this->actingAs($this->guide)->get('/admin/vendor/tour-messages/'.$booking->uuid)
        ->assertOk()
        ->assertSee('Xin chào guide!');
});

test('vendor tour CRUD routes are removed (guide-only flow)', function (): void {
    $this->actingAs($this->guide)->get('/admin/vendor/tours')->assertNotFound();
    $this->actingAs($this->guide)->get('/admin/vendor/tours/create')->assertNotFound();
    $this->actingAs($this->guide)->post('/admin/vendor/tours', [])->assertNotFound();
});

test('cross-guide tour isolation in blade', function (): void {
    $other = User::factory()->create([
        'email' => 'other-guide@test.local',
        'role' => Role::GUIDE, 'status' => 'active',
    ]);
    $other->assignRole('guide');

    $booking = ($this->createBooking)($this->provider, 'Isolated tour');

    // Other guide cannot read the inbox row, invoice or message thread.
    $this->actingAs($other)->get('/admin/vendor/tour-bookings')
        ->assertOk()
        ->assertDontSee('Isolated tour');
    $this->actingAs($other)->get('/admin/vendor/tour-bookings/'.$booking->uuid.'/invoice')
        ->assertForbidden();
    $this->actingAs($other)->get('/admin/vendor/tour-messages/'.$booking->uuid)
        ->assertNotFound();
    $this->actingAs($other)->get('/admin/vendor/tour-messages')
        ->assertOk()
        ->assertDontSee('Isolated tour');
});
