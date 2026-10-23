<?php

use App\Enums\PaymentStatus;
use App\Enums\TourBookingStatus;
use App\Enums\TourSlotStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use App\Repositories\Contracts\TourAvailabilitySlotRepositoryInterface;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Repositories\Contracts\TourDisputeRepositoryInterface;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use App\Repositories\Contracts\TourProductRepositoryInterface;
use App\Repositories\Contracts\TourProviderRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Tour Repo Vendor', 'email' => 'tour-repo-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Tour Repo Customer', 'email' => 'tour-repo-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Ha Giang', 'slug' => 'ha-giang-repo',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $this->provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Repo Guide', 'status' => 'approved',
    ]);
    $this->tour = TourProduct::create([
        'provider_id' => $this->provider->id, 'province_id' => $province->id,
        'title' => 'Repo Tour', 'base_fixed' => 300000,
        'base_price_hourly' => 150000, 'base_price_daily' => 1200000,
        'transport_fee' => 200000, 'status' => 'published',
    ]);
    $this->slot = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available',
    ]);
    $this->otherVendor = User::create([
        'name' => 'Other Vendor', 'email' => 'tour-repo-other@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $this->otherProvider = TourProvider::create([
        'vendor_id' => $this->otherVendor->id, 'business_name' => 'Other Guide', 'status' => 'approved',
    ]);
});

test('tour booking repository creates and finds bookings', function (): void {
    $repos = app(TourBookingRepositoryInterface::class);

    $booking = $repos->create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $this->slot->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->tour->province_id,
        'start_at' => Carbon::tomorrow()->setTime(8, 0), 'end_at' => Carbon::tomorrow()->setTime(12, 0),
        'pricing_mode' => 'hour', 'duration_value' => 4, 'subtotal' => 900000,
        'transport_fee' => 200000, 'discount_amount' => 0, 'tax_amount' => 88000,
        'total_price' => 1188000, 'currency' => 'VND',
        'status' => TourBookingStatus::PENDING_PAYMENT->value,
    ]);

    expect($booking->uuid)->not->toBeEmpty()
        ->and($repos->findById($booking->id)?->id)->toBe($booking->id)
        ->and($repos->findByIdForUpdate($booking->id)?->id)->toBe($booking->id)
        ->and($repos->findByUuid($booking->uuid)?->id)->toBe($booking->id)
        ->and($repos->findByUuidOrFail($booking->uuid)->id)->toBe($booking->id);

    $updated = $repos->update($booking, ['status' => TourBookingStatus::CONFIRMED->value]);
    expect($updated->status)->toBe(TourBookingStatus::CONFIRMED->value);

    $with = $repos->findByUuid($booking->uuid, ['tour', 'slot']);
    expect($with->tour->id)->toBe($this->tour->id);

    $page = $repos->paginateByCustomer($this->customer->id, ['tour'], 15);
    expect($page->total())->toBe(1);

    $providerPage = $repos->paginateForProviders([$this->provider->id], null, null, null, 15);
    expect($providerPage->total())->toBe(1);
});

test('tour slot repository locks and releases expired holds', function (): void {
    $slots = app(TourAvailabilitySlotRepositoryInterface::class);

    $held = TourAvailabilitySlot::create([
        'tour_id' => $this->tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '13:00:00', 'end_time' => '17:00:00',
        'status' => TourSlotStatus::HELD->value, 'held_until' => Carbon::now()->subMinutes(5),
    ]);

    expect($slots->findById($held->id)?->id)->toBe($held->id)
        ->and($slots->findWithTourProvider($held->id)?->tour?->id)->toBe($this->tour->id);

    $released = $slots->releaseExpiredHolds();
    expect($released)->toBeGreaterThanOrEqual(1)
        ->and($held->fresh()->status)->toBe(TourSlotStatus::AVAILABLE->value);

    $list = $slots->listForTourRange($this->tour->id, Carbon::tomorrow()->toDateString(), Carbon::tomorrow()->toDateString());
    expect($list->count())->toBeGreaterThanOrEqual(2);
    expect($list->count())->toBeGreaterThanOrEqual(0);

    $guideSlot = $slots->findGuideSlot($this->provider->uuid, Carbon::tomorrow()->toDateString());
    expect($guideSlot)->not->toBeNull();
});

test('tour payment repository finds by txn ref', function (): void {
    $bookings = app(TourBookingRepositoryInterface::class);
    $payments = app(TourPaymentRepositoryInterface::class);

    $booking = $bookings->create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $this->slot->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->tour->province_id,
        'start_at' => Carbon::tomorrow()->setTime(8, 0), 'end_at' => Carbon::tomorrow()->setTime(12, 0),
        'pricing_mode' => 'hour', 'duration_value' => 4, 'subtotal' => 900000,
        'transport_fee' => 200000, 'discount_amount' => 0, 'tax_amount' => 88000,
        'total_price' => 1188000, 'currency' => 'VND',
        'status' => TourBookingStatus::PENDING_PAYMENT->value,
    ]);

    $payment = $payments->create([
        'tour_booking_id' => $booking->id, 'amount' => 1188000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'tour-repo-txn-1',
        'status' => PaymentStatus::PENDING->value,
    ]);

    expect($payments->findById($payment->id)?->id)->toBe($payment->id)
        ->and($payments->findByTxnRef('tour-repo-txn-1')?->id)->toBe($payment->id);

    $updated = $payments->update($payment, ['status' => PaymentStatus::COMPLETED->value]);
    expect($updated->status)->toBe(PaymentStatus::COMPLETED->value);
});

test('tour product repository distinguishes published and draft', function (): void {
    $products = app(TourProductRepositoryInterface::class);

    $draft = TourProduct::create([
        'provider_id' => $this->provider->id, 'province_id' => $this->tour->province_id,
        'title' => 'Draft Tour', 'base_fixed' => 100000, 'status' => 'draft',
    ]);

    expect($products->findPublishedById($this->tour->id)?->id)->toBe($this->tour->id)
        ->and($products->findPublishedById($draft->id))->toBeNull()
        ->and($products->findByIdOrFail($draft->id)->id)->toBe($draft->id);

    $loaded = $products->loadProvinceCountry($this->tour);
    expect($loaded->province)->not->toBeNull();
});

test('tour dispute repository enforces unique booking', function (): void {
    $bookings = app(TourBookingRepositoryInterface::class);
    $disputes = app(TourDisputeRepositoryInterface::class);

    $booking = $bookings->create([
        'customer_id' => $this->customer->id, 'tour_id' => $this->tour->id,
        'slot_id' => $this->slot->id, 'provider_id' => $this->provider->id,
        'province_id' => $this->tour->province_id,
        'start_at' => Carbon::tomorrow()->setTime(8, 0), 'end_at' => Carbon::tomorrow()->setTime(12, 0),
        'pricing_mode' => 'hour', 'duration_value' => 4, 'subtotal' => 900000,
        'transport_fee' => 200000, 'discount_amount' => 0, 'tax_amount' => 88000,
        'total_price' => 1188000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);

    $dispute = $disputes->create([
        'tour_booking_id' => $booking->id, 'status' => 'open',
        'contact_name' => 'Repo Customer',
    ]);
    expect($dispute->tour_booking_id)->toBe($booking->id);

    expect(fn () => $disputes->create([
        'tour_booking_id' => $booking->id, 'status' => 'open',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('tour provider repository returns ids for vendor', function (): void {
    $providers = app(TourProviderRepositoryInterface::class);

    $ids = $providers->providerIdsForVendor($this->vendor->id);
    expect($ids->all())->toContain($this->provider->id)
        ->and($ids->all())->not->toContain($this->otherProvider->id);

    $otherIds = $providers->providerIdsForVendor($this->otherVendor->id);
    expect($otherIds->all())->toContain($this->otherProvider->id);
});
