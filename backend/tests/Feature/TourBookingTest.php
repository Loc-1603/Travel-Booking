<?php

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourMessage;
use App\Models\TourPayment;
use App\Models\TourProduct;
use App\Models\TourProvince;
use App\Models\TourProvider;
use App\Models\User;
use App\Services\TourVnpayAdapter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    $this->vendor = User::create([
        'name' => 'Tour Vendor', 'email' => 'tour-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Tour Customer', 'email' => 'tour-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Hà Giang', 'slug' => 'ha-giang-test',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Test Guide',
        'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Test 1vs1 tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
    $this->slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
});

test('tour provinces and search are public', function (): void {
    $this->getJson('/api/v1/tour-provinces')->assertOk();
    $this->getJson('/api/v1/tours')->assertOk()
        ->assertJsonPath('data.data.0.title', 'Test 1vs1 tour');
    $this->getJson('/api/v1/tours/'.$this->tour->uuid)->assertOk();
    $this->getJson('/api/v1/tours/'.$this->tour->uuid.'/availability')->assertOk();
});

test('guest cannot create tour booking (login required)', function (): void {
    $this->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 4,
    ])->assertUnauthorized();
});

test('customer previews fixed+hourly price and books once; second booking fails', function (): void {
    // total = 300000 + 150000*4 + 200000 = 1100000, tax 8% = 88000 -> 1188000
    $preview = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings/preview', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 4,
    ])->assertOk();

    // subtotal = 300000 + 150000*4 = 900000; + transport 200000, tax 8% of 1100000 = 88000 -> total 1188000
    expect((float) $preview->json('data.subtotal'))->toBe(900000.0)
        ->and((float) $preview->json('data.add_on_amount'))->toBe(200000.0)
        ->and((float) $preview->json('data.total'))->toBe(1188000.0);

    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 4,
    ])->assertCreated();

    $uuid = $store->json('data.booking.uuid');
    expect(TourBooking::where('uuid', $uuid)->first()->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value)
        ->and($this->slot->fresh()->status)->toBe('booked');

    // Same slot again -> 422 UNAVAILABLE (capacity 1)
    $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertStatus(422);

    // Owner can view + create checkout session
    $this->actingAs($this->customer)->getJson('/api/v1/tour-bookings/'.$uuid)->assertOk();
    $checkout = $this->actingAs($this->customer)
        ->postJson('/api/v1/tour-bookings/'.$uuid.'/checkout-session')
        ->assertOk();
    expect($checkout->json('data.checkout_url'))->toContain('vnp_TxnRef=tour_');

    // Cancel releases the slot
    $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings/'.$uuid.'/cancel')->assertOk();
    expect($this->slot->fresh()->status)->toBe('available');
});

test('tour vnpay ipn confirms booking via job', function (): void {
    // NOTE: no Event::fake() — it would swallow Eloquent creating hooks (HasUuid).
    $bookingResp = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'day', 'duration_value' => 1,
    ]);
    $booking = $bookingResp->assertCreated();
    $uuid = $booking->json('data.booking.uuid');
    $tourBooking = TourBooking::where('uuid', $uuid)->first();

    $adapter = app(TourVnpayAdapter::class);
    $result = $adapter->createPaymentUrl($tourBooking, '127.0.0.1');
    $payment = TourPayment::find($result['payment_id']);

    $params = [
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_Amount' => (int) round((float) $payment->amount) * 100,
        'vnp_TxnRef' => $payment->external_id,
        'vnp_TransactionNo' => '14'.random_int(100000, 999999),
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
    ];
    $params['vnp_SecureHash'] = app(\App\Services\VnpayService::class)->signature($params);

    $this->getJson('/api/v1/payments/tour-vnpay-ipn?'.http_build_query($params))
        ->assertOk()
        ->assertJsonPath('RspCode', '00');

    // QUEUE_CONNECTION=sync in phpunit: job ran inline -> booking confirmed.
    expect($tourBooking->fresh()->status)->toBe(TourBookingStatus::CONFIRMED->value)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED->value);

    // Hotel payments table untouched by tour flow
    expect(\App\Models\Payment::count())->toBe(0);
});

test('other customer cannot view booking, owner can dispute and download invoice', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    $stranger = User::create([
        'name' => 'Stranger', 'email' => 'stranger@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);

    // Policy: stranger gets 403 (not 404)
    $this->actingAs($stranger)->getJson('/api/v1/tour-bookings/'.$uuid)->assertForbidden();
    $this->actingAs($stranger)->postJson('/api/v1/tour-bookings/'.$uuid.'/cancel')->assertForbidden();

    // Dispute before payment -> 422 INVALID_STATUS
    $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings/'.$uuid.'/dispute', [
        'customer_notes' => 'The guide never showed up at the meeting point today.',
    ])->assertStatus(422);

    // Confirm booking, then dispute works once
    TourBooking::where('uuid', $uuid)->update(['status' => TourBookingStatus::CONFIRMED->value]);
    $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings/'.$uuid.'/dispute', [
        'customer_notes' => 'The guide never showed up at the meeting point today.',
    ])->assertCreated();
    $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings/'.$uuid.'/dispute', [
        'customer_notes' => 'Trying to open a second dispute for the same booking.',
    ])->assertStatus(422);

    // Invoice is HTML and contains the booking reference
    $invoice = $this->actingAs($this->customer)->get('/api/v1/tour-bookings/'.$uuid.'/invoice')->assertOk();
    expect($invoice->headers->get('Content-Type'))->toContain('text/html');
    expect($invoice->getContent())->toContain($uuid);
    $this->actingAs($stranger)->get('/api/v1/tour-bookings/'.$uuid.'/invoice')->assertForbidden();
});

test('customer and vendor can chat in booking thread, stranger cannot', function (): void {
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    // Chưa thanh toán (pending_payment) -> chat bị chặn 403 PAYMENT_REQUIRED.
    $this->actingAs($this->customer)->postJson("/api/v1/tour-bookings/{$uuid}/messages", [
        'body' => 'Hello before payment',
    ])->assertForbidden()
        ->assertJsonPath('code', 'PAYMENT_REQUIRED');
    $this->actingAs($this->customer)->getJson("/api/v1/tour-bookings/{$uuid}/messages")->assertForbidden();

    // Giả lập đã thanh toán -> chat mở.
    TourBooking::where('uuid', $uuid)->update(['status' => TourBookingStatus::CONFIRMED->value]);

    // Customer sends first message
    $this->actingAs($this->customer)->postJson("/api/v1/tour-bookings/{$uuid}/messages", [
        'body' => 'Hello, where should we meet tomorrow?',
    ])->assertCreated();

    // Vendor (provider owner) reads + replies
    $list = $this->actingAs($this->vendor)->getJson("/api/v1/tour-bookings/{$uuid}/messages")->assertOk();
    expect($list->json('data.0.body'))->toBe('Hello, where should we meet tomorrow?');
    $this->actingAs($this->vendor)->postJson("/api/v1/tour-bookings/{$uuid}/messages", [
        'body' => 'Hi! Meet me at the Old Quarter gate at 8am.',
    ])->assertCreated();

    // Validation: empty body rejected
    $this->actingAs($this->customer)->postJson("/api/v1/tour-bookings/{$uuid}/messages", [
        'body' => '',
    ])->assertStatus(422);

    // Stranger gets 403
    $stranger = User::create([
        'name' => 'Stranger2', 'email' => 'stranger2@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->actingAs($stranger)->getJson("/api/v1/tour-bookings/{$uuid}/messages")->assertForbidden();
});

test('messages track read/unread status on both sides', function (): void {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    $this->vendor->forceFill(['email_verified_at' => now()])->save();
    $this->vendor->assignRole('vendor');

    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');
    TourBooking::where('uuid', $uuid)->update(['status' => TourBookingStatus::CONFIRMED->value]);
    $booking = TourBooking::where('uuid', $uuid)->firstOrFail();

    // Customer messages the vendor -> vendor side unread.
    $this->actingAs($this->customer)->postJson("/api/v1/tour-bookings/{$uuid}/messages", [
        'body' => 'Hello from customer',
    ])->assertCreated();
    $customerMessage = TourMessage::where('tour_booking_id', $booking->id)->first();
    expect($customerMessage->read_at)->toBeNull();

    // Vendor inbox shows 1 unread badge.
    $this->actingAs($this->vendor)->get('/admin/vendor/tour-messages')
        ->assertOk()
        ->assertSee('chưa đọc', false);

    // Vendor opens the thread via blade -> customer messages marked read.
    $this->actingAs($this->vendor)->get("/admin/vendor/tour-messages/{$uuid}")->assertOk();
    expect($customerMessage->fresh()->read_at)->not->toBeNull();

    // Vendor replies -> customer side unread until customer reads.
    $this->actingAs($this->vendor)->post("/admin/vendor/tour-messages/{$uuid}/reply", [
        'body' => 'Hi customer',
    ])->assertRedirect();
    $vendorMessage = TourMessage::where('tour_booking_id', $booking->id)
        ->where('sender_id', $this->vendor->id)
        ->firstOrFail();
    expect($vendorMessage->read_at)->toBeNull();

    // Customer reads the thread -> vendor messages marked read.
    $list = $this->actingAs($this->customer)->getJson("/api/v1/tour-bookings/{$uuid}/messages")->assertOk();
    expect(collect($list->json('data'))->where('sender_id', $this->vendor->id)->first()['read_at'])->not->toBeNull();
    expect($vendorMessage->fresh()->read_at)->not->toBeNull();
});

test('broadcast auth allows booking parties, rejects stranger', function (): void {
    // phpunit.xml forces BROADCAST_CONNECTION=null (empty 200 body); use reverb
    // driver here — channel signing is local HMAC, no socket server needed.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);
    // Channels are registered on the boot-time (null) driver instance —
    // re-register them on the reverb driver used below.
    require base_path('routes/channels.php');

    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $this->slot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');

    $payload = ['socket_id' => '123.456', 'channel_name' => "private-tour.booking.{$uuid}"];

    // Customer + vendor get Pusher auth signatures (sanctum guard, like the SPA)
    $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/broadcasting/auth', $payload)->assertOk()
        ->assertJsonStructure(['auth']);
    $this->actingAs($this->vendor, 'sanctum')->postJson('/api/v1/broadcasting/auth', $payload)->assertOk()
        ->assertJsonStructure(['auth']);

    $stranger = User::create([
        'name' => 'Stranger3', 'email' => 'stranger3@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->actingAs($stranger, 'sanctum')->postJson('/api/v1/broadcasting/auth', $payload)->assertForbidden();

    // Guest (no token) is rejected (401 from sanctum or 403 from channel gate)
    $this->postJson('/api/v1/broadcasting/auth', $payload)->assertStatus(403);
});

test('saved tours crud', function (): void {
    $this->actingAs($this->customer)->getJson('/api/v1/saved-tours')->assertOk();

    $this->actingAs($this->customer)->postJson('/api/v1/saved-tours', [
        'tour_id' => $this->tour->id,
    ])->assertCreated();

    // Idempotent re-save
    $this->actingAs($this->customer)->postJson('/api/v1/saved-tours', [
        'tour_id' => $this->tour->id,
    ])->assertCreated();
    expect(\App\Models\SavedTour::count())->toBe(1);

    $this->actingAs($this->customer)->deleteJson('/api/v1/saved-tours/'.$this->tour->id)->assertNoContent();
    expect(\App\Models\SavedTour::count())->toBe(0);
});

test('guide 1-day flow: availability, preview and booking by provider', function (): void {
    $providerUuid = $this->tour->provider->uuid;
    $date = Carbon::tomorrow()->toDateString();

    // Lịch trống theo ngày của guide (public).
    $avail = $this->getJson("/api/v1/tour-providers/{$providerUuid}/availability?date={$date}")->assertOk();
    $slots = $avail->json('data');
    expect($slots)->not->toBeEmpty()
        ->and((int) $slots[0]['id'])->toBe($this->slot->id)
        // Giá 1 ngày = base_price_daily (không override).
        ->and((float) $slots[0]['price'])->toBe(1200000.0);

    // Preview giá 1 ngày (không cần slot_id — backend tự resolve theo ngày).
    $preview = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings/guide-preview', [
        'provider_uuid' => $providerUuid, 'travel_date' => $date,
    ])->assertOk();
    // subtotal = 300000 + 1200000*1 = 1500000; + transport 200000 = 1700000; tax 8% = 136000 -> 1836000
    expect((float) $preview->json('data.subtotal'))->toBe(1500000.0)
        ->and((float) $preview->json('data.total'))->toBe(1836000.0)
        ->and($preview->json('data.pricing_mode'))->toBe('day');

    // Đặt theo guide (không cần slot_id).
    $store = $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings/guide', [
        'provider_uuid' => $providerUuid, 'travel_date' => $date,
    ])->assertCreated();
    $uuid = $store->json('data.booking.uuid');
    $booking = TourBooking::where('uuid', $uuid)->first();
    expect($booking->pricing_mode)->toBe('day')
        ->and((int) $booking->duration_value)->toBe(1)
        ->and($booking->status)->toBe(TourBookingStatus::PENDING_PAYMENT->value);

    // Slot của guide khác -> 422.
    $otherVendor = User::create([
        'name' => 'Other Vendor', 'email' => 'other-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $otherProvider = \App\Models\TourProvider::create([
        'vendor_id' => $otherVendor->id, 'business_name' => 'Other Guide', 'status' => 'approved',
    ]);
    $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings/guide-preview', [
        'provider_uuid' => $otherProvider->uuid, 'slot_id' => $this->slot->id, 'travel_date' => $date,
    ])->assertStatus(422);
});

test('slot must belong to the booked tour', function (): void {
    $otherTour = TourProduct::create([
        'provider_id' => $this->tour->provider_id, 'province_id' => $this->tour->province_id,
        'title' => 'Other tour', 'base_fixed' => 100000,
        'base_price_hourly' => 50000, 'base_price_daily' => 400000,
        'status' => 'published',
    ]);
    $otherSlot = TourAvailabilitySlot::create([
        'tour_id' => $otherTour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '14:00:00', 'end_time' => '16:00:00', 'status' => 'available',
    ]);

    $this->actingAs($this->customer)->postJson('/api/v1/tour-bookings', [
        'tour_id' => $this->tour->id, 'slot_id' => $otherSlot->id,
        'pricing_mode' => 'hour', 'duration_value' => 2,
    ])->assertStatus(422);
});
