<?php

use App\Actions\PublishOutboxEvents;
use App\Events\TourPaymentConfirmed;
use App\Models\OutboxEvent;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourPayment;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;
use App\Repositories\Contracts\OutboxEventRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    DB::statement('PRAGMA foreign_keys = OFF');
    $this->repo = app(OutboxEventRepositoryInterface::class);
    $this->publisher = app(PublishOutboxEvents::class);
});

test('publisher publishes a pending event and marks it published', function (): void {
$payment = makePublisherTourPayment();

    $event = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:'.$payment->id,
        'event_type' => TourPaymentConfirmed::class,
        'event_version' => 1,
        'payload' => ['payment_id' => $payment->id],
        'available_at' => now(),
    ]);

    Event::fake();

    $result = $this->publisher->handle(10, 90);

    expect($result['claimed'])->toBe(1)
        ->and($result['published'])->toBe(1)
        ->and($result['retried'])->toBe(0)
        ->and($result['failed'])->toBe(0)
        ->and($event->fresh()->published_at)->not->toBeNull();

    Event::assertDispatched(TourPaymentConfirmed::class);
});

test('publisher schedules retry when payment cannot be found', function (): void {
    $event = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:999999',
        'event_type' => TourPaymentConfirmed::class,
        'payload' => ['payment_id' => 999999],
        'available_at' => now(),
    ]);

    $result = $this->publisher->handle(10, 90);

    $fresh = $event->fresh();

    expect($result['claimed'])->toBe(1)
        ->and($result['published'])->toBe(0)
        ->and($result['retried'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and($fresh->attempts)->toBe(1)
        ->and($fresh->published_at)->toBeNull()
        ->and($fresh->available_at->isFuture())->toBeTrue()
        ->and($fresh->last_error)->toContain('Tour payment not found');
});

test('publisher marks an event failed after exhausting retries', function (): void {
    $event = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:999998',
        'event_type' => TourPaymentConfirmed::class,
        'payload' => ['payment_id' => 999998],
        'available_at' => now(),
    ]);
    $event->update(['attempts' => 2]);

    $result = $this->publisher->handle(10, 90);

    $fresh = $event->fresh();

    expect($result['claimed'])->toBe(1)
        ->and($result['published'])->toBe(0)
        ->and($result['retried'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($fresh->attempts)->toBe(3)
        ->and($fresh->published_at)->toBeNull()
        ->and($fresh->last_error)->not->toBeNull();
});

test('publisher does not claim failed events', function (): void {
    $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:999997',
        'event_type' => TourPaymentConfirmed::class,
        'payload' => ['payment_id' => 999997],
        'available_at' => now(),
    ])->update(['attempts' => 3]);

    $result = $this->publisher->handle(10, 90);

    expect($result['claimed'])->toBe(0);
});

test('publisher skips already published events', function (): void {
$payment = makePublisherTourPayment();

    $event = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:'.$payment->id,
        'event_type' => TourPaymentConfirmed::class,
        'payload' => ['payment_id' => $payment->id],
        'available_at' => now(),
    ]);
    $this->repo->markPublished($event);

    $result = $this->publisher->handle(10, 90);

    expect($result['claimed'])->toBe(0)
        ->and($result['published'])->toBe(0);
});

test('replay resets a failed event', function (): void {
    $event = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:999996',
        'event_type' => TourPaymentConfirmed::class,
        'payload' => ['payment_id' => 999996],
        'available_at' => now(),
    ]);
    $event->update(['attempts' => 3, 'last_error' => 'boom']);

    expect($this->publisher->replay($event->event_id))->toBeTrue();

    $fresh = $event->fresh();
    expect($fresh->attempts)->toBe(0)
        ->and($fresh->last_error)->toBeNull()
        ->and($fresh->published_at)->toBeNull()
        ->and($fresh->available_at->isPast() || $fresh->available_at->isCurrentMinute())->toBeTrue();
});

test('replay returns false for missing event', function (): void {
    expect($this->publisher->replay('does-not-exist'))->toBeFalse();
});
function makePublisherTourPayment(): TourPayment
{
    $customer = User::create([
        'name' => 'Publisher Customer', 'email' => 'publisher-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $vendor = User::create([
        'name' => 'Publisher Vendor', 'email' => 'publisher-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Publisher Province', 'slug' => 'publisher-province',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $vendor->id, 'business_name' => 'Publisher Guide', 'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Publisher tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'booked',
    ]);
    $booking = TourBooking::create([
        'customer_id' => $customer->id, 'tour_id' => $tour->id,
        'slot_id' => $slot->id, 'provider_id' => $provider->id,
        'province_id' => $province->id, 'start_at' => Carbon::tomorrow()->setTime(8, 0),
        'end_at' => Carbon::tomorrow()->setTime(10, 0), 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => 'confirmed',
    ]);

    return TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => 500000,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'status' => 'completed',
    ]);
}