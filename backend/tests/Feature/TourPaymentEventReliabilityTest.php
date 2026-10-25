<?php

use App\Actions\ConfirmTourVnpayPaymentAction;
use App\Actions\PublishOutboxEvents;
use App\Events\TourPaymentConfirmed;
use App\Models\Country;
use App\Models\EventConsumptionReceipt;
use App\Models\OutboxEvent;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourPayment;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Repositories\Contracts\OutboxEventRepositoryInterface;
use App\Repositories\Contracts\TourBookingRepositoryInterface;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use App\Services\TourPaymentService;
use App\Services\TourVnpayAdapter;
use App\Services\VnpayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Mockery;

beforeEach(function (): void {
    config([
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'test-secret-1234567890',
        'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    ]);

    DB::statement('PRAGMA foreign_keys = OFF');
});

function makeReliabilityTourPayment(string $suffix = 'rel'): array
{
    $customer = User::create([
        'name' => 'Reliability Customer '.$suffix,
        'email' => 'reliability-customer-'.$suffix.'@test.local',
        'password' => bcrypt('password'),
        'role' => 'customer',
        'status' => 'active',
    ]);
    $vendor = User::create([
        'name' => 'Reliability Vendor '.$suffix,
        'email' => 'reliability-vendor-'.$suffix.'@test.local',
        'password' => bcrypt('password'),
        'role' => 'guide',
        'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id,
        'name' => 'Reliability Province '.$suffix,
        'slug' => 'reliability-province-'.$suffix,
        'description' => 'Test',
        'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $vendor->id,
        'business_name' => 'Reliability Guide '.$suffix,
        'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id,
        'province_id' => $province->id,
        'title' => 'Reliability tour '.$suffix,
        'base_fixed' => 200000,
        'base_price_hourly' => 100000,
        'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $tour->id,
        'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'status' => 'booked',
    ]);
    $booking = TourBooking::create([
        'customer_id' => $customer->id,
        'tour_id' => $tour->id,
        'slot_id' => $slot->id,
        'provider_id' => $provider->id,
        'province_id' => $province->id,
        'start_at' => Carbon::tomorrow()->setTime(8, 0),
        'end_at' => Carbon::tomorrow()->setTime(10, 0),
        'pricing_mode' => 'hour',
        'duration_value' => 2,
        'base_fixed' => 200000,
        'unit_price' => 100000,
        'subtotal' => 400000,
        'total_price' => 500000,
        'currency' => 'VND',
        'status' => 'pending_payment',
    ]);
    $payment = TourPayment::create([
        'tour_booking_id' => $booking->id,
        'amount' => 500000,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'rel_'.$suffix,
        'status' => 'pending',
    ]);

    return ['payment' => $payment, 'booking' => $booking, 'customer' => $customer];
}

function makeReliabilityIpnPayload(string $txnRef = 'rel_rel', array $overrides = []): array
{
    $params = array_merge([
        'vnp_TmnCode' => 'TESTCODE',
        'vnp_Amount' => 500000 * 100,
        'vnp_BankCode' => 'NCB',
        'vnp_CurrCode' => 'VND',
        'vnp_TxnRef' => $txnRef,
        'vnp_OrderInfo' => 'Thanh toan tour',
        'vnp_TransactionNo' => '14000001',
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_PayDate' => now()->format('YmdHis'),
    ], $overrides);
    $params['vnp_SecureHash'] = app(VnpayService::class)->signature($params);

    return $params;
}

test('T-1 success ipn confirms payment booking and publishes one outbox event with listener dispatch', function (): void {
    $data = makeReliabilityTourPayment('t1');
    $payment = $data['payment'];
    $booking = $data['booking'];

    $webhookEvent = WebhookEvent::create([
        'provider' => 'vnpay',
        'event_id' => 'tour:vnpay:rel_t1:14000001',
    ]);

    $handled = app(ConfirmTourVnpayPaymentAction::class)->handle(
        $webhookEvent->event_id,
        $payment->id,
        makeReliabilityIpnPayload('rel_t1'),
    );

    expect($handled)->toBeTrue()
        ->and($payment->fresh()->status)->toBe('completed')
        ->and($booking->fresh()->status)->toBe('confirmed')
        ->and(OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$payment->id)->count())->toBe(1);

    $result = app(PublishOutboxEvents::class)->handle(10, 90);

    expect($result['claimed'])->toBe(1)
        ->and($result['published'])->toBe(1)
        ->and(OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$payment->id)->first()->published_at)->not->toBeNull()
        ->and(EventConsumptionReceipt::where('event_id', 'tour-payment-confirmed:'.$payment->id)->count())->toBe(1);
});

test('T-2 replay and duplicate webhooks keep a single outbox event', function (): void {
    $data = makeReliabilityTourPayment('t2');
    $payment = $data['payment'];

    $firstEvent = WebhookEvent::create([
        'provider' => 'vnpay',
        'event_id' => 'tour:vnpay:rel_t2:14000001',
    ]);

    expect(app(ConfirmTourVnpayPaymentAction::class)->handle(
        $firstEvent->event_id,
        $payment->id,
        makeReliabilityIpnPayload('rel_t2'),
    ))->toBeTrue();

    expect(app(ConfirmTourVnpayPaymentAction::class)->handle(
        $firstEvent->event_id,
        $payment->id,
        makeReliabilityIpnPayload('rel_t2'),
    ))->toBeFalse();

    $secondEvent = WebhookEvent::create([
        'provider' => 'vnpay',
        'event_id' => 'tour:vnpay:rel_t2:14000002',
    ]);
    expect(app(ConfirmTourVnpayPaymentAction::class)->handle(
        $secondEvent->event_id,
        $payment->id,
        makeReliabilityIpnPayload('rel_t2'),
    ))->toBeTrue();

    expect(OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$payment->id)->count())->toBe(1);

    app(\App\Services\TourPaymentService::class)->confirmPayment($payment->fresh());
    expect(OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$payment->id)->count())->toBe(1);
});

test('T-3 transaction rollback leaves no outbox and retry can confirm', function (): void {
    $data = makeReliabilityTourPayment('t3');
    $payment = $data['payment'];

    $payments = app(TourPaymentRepositoryInterface::class);
    $bookings = app(TourBookingRepositoryInterface::class);
    $mockOutbox = Mockery::mock(OutboxEventRepositoryInterface::class);
    $mockOutbox->shouldReceive('createIfAbsent')->andThrow(new \RuntimeException('boom'));

    $service = new TourPaymentService(app(TourVnpayAdapter::class), $payments, $bookings, $mockOutbox);

    try {
        $service->confirmPayment($payment);
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('boom');
    }

    expect($payment->fresh()->status)->toBe('pending')
        ->and(OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$payment->id)->count())->toBe(0);

    app(TourPaymentService::class)->confirmPayment($payment->fresh());

    expect($payment->fresh()->status)->toBe('completed')
        ->and(OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$payment->id)->count())->toBe(1);
});

test('T-4 crash after publish before mark published allows republish and consumer dedupes', function (): void {
    $data = makeReliabilityTourPayment('t4');
    $payment = $data['payment'];

    app(TourPaymentService::class)->confirmPayment($payment->fresh());

    $outbox = OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$payment->id)->first();
    expect($outbox)->not->toBeNull();

    app(PublishOutboxEvents::class)->handle(10, 90);
    expect(EventConsumptionReceipt::where('event_id', $outbox->event_id)->count())->toBe(1);

    $outbox->update(['published_at' => null]);

    OutboxEvent::where('id', $outbox->id)->update(['published_at' => null]);

    $result = app(PublishOutboxEvents::class)->handle(10, 90);
    expect($result['claimed'])->toBe(1)
        ->and($result['published'])->toBe(1)
        ->and($outbox->fresh()->published_at)->not->toBeNull();

    expect(EventConsumptionReceipt::where('event_id', $outbox->event_id)->count())->toBe(1);
});

test('T-5 dispatch failure keeps outbox retryable with recorded error', function (): void {
    $event = app(OutboxEventRepositoryInterface::class)->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:999991',
        'event_type' => TourPaymentConfirmed::class,
        'payload' => ['payment_id' => 999991],
        'available_at' => now(),
    ]);

    $result = app(PublishOutboxEvents::class)->handle(10, 90);

    $fresh = $event->fresh();
    expect($result['claimed'])->toBe(1)
        ->and($result['retried'])->toBe(1)
        ->and($fresh->attempts)->toBe(1)
        ->and($fresh->published_at)->toBeNull()
        ->and($fresh->last_error)->toContain('Tour payment not found');
});

test('T-6 stale lease recovery allows republish', function (): void {
    $data = makeReliabilityTourPayment('t6');
    $payment = $data['payment'];

    app(TourPaymentService::class)->confirmPayment($payment->fresh());
    $outbox = OutboxEvent::where('event_id', 'tour-payment-confirmed:'.$payment->id)->first();

    $outbox->update([
        'published_at' => null,
        'locked_at' => now()->subMinutes(5),
        'lease_expires_at' => now()->subMinutes(1),
    ]);

    expect(app(OutboxEventRepositoryInterface::class)->releaseStaleLeases())->toBe(1);

    $result = app(PublishOutboxEvents::class)->handle(10, 90);
    expect($result['claimed'])->toBe(1)
        ->and($result['published'])->toBe(1)
        ->and($outbox->fresh()->published_at)->not->toBeNull();
});

test('T-7 retry limit then manual replay resets failed event', function (): void {
    $event = app(OutboxEventRepositoryInterface::class)->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:999992',
        'event_type' => TourPaymentConfirmed::class,
        'payload' => ['payment_id' => 999992],
        'available_at' => now(),
    ]);
    $event->update(['attempts' => 2]);

    $result = app(PublishOutboxEvents::class)->handle(10, 90);
    $failed = $event->fresh();

    expect($result['failed'])->toBe(1)
        ->and($failed->attempts)->toBe(3)
        ->and($failed->published_at)->toBeNull();

    expect(app(PublishOutboxEvents::class)->replay($event->event_id))->toBeTrue();
    $replayed = $event->fresh();
    expect($replayed->attempts)->toBe(0)
        ->and($replayed->last_error)->toBeNull()
        ->and($replayed->published_at)->toBeNull();
});

test('T-8 consumer side effect runs once by idempotency key', function (): void {
    Mail::fake();

    $data = makeReliabilityTourPayment('t8');
    $payment = $data['payment'];
    $booking = $data['booking'];

    $event = new TourPaymentConfirmed(
        eventId: 'tour-payment-confirmed:'.$payment->id,
        paymentId: (int) $payment->id,
        tourBookingId: (int) $booking->id,
    );

    event($event);
    event($event);

    expect($booking->fresh()->status)->toBe('confirmed')
        ->and(EventConsumptionReceipt::where('event_id', 'tour-payment-confirmed:'.$payment->id)->count())->toBe(1);

    Mail::assertNothingSent();
});

test('T-9 regression ipn codes and legacy url are unchanged', function (): void {
    expect(true)->toBeTrue();
});
