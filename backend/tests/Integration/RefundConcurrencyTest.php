<?php

use App\Actions\CreateBookingAction;
use App\DTOs\CreateBookingData;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Room;
use App\Models\User;
use App\Services\RefundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

uses(Tests\TestCase::class, DatabaseTruncation::class);

beforeEach(function (): void {
    if (! in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
        $this->markTestSkipped('Concurrency tests require a lock-compatible database (mysql/pgsql).');
    }
    if (env('RUN_CONCURRENCY_TESTS') !== '1') {
        $this->markTestSkipped('Set RUN_CONCURRENCY_TESTS=1 to run integration concurrency tests.');
    }

    $this->vendor = User::create([
        'name' => 'Refund Concurrency Vendor', 'email' => 'refund-conc-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Refund Concurrency Customer', 'email' => 'refund-conc-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Refund Concurrency Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam', 'status' => 'active',
        'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Refund Concurrency Room',
        'capacity' => 2, 'base_price' => 500000, 'total_rooms' => 5,
    ]);
});

function refundConcurrencyEnv(): array
{
    $driver = DB::getDriverName();
    $connection = config('database.connections.'.$driver);

    $env = [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => $driver,
        'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        'QUEUE_CONNECTION' => 'database',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'MAIL_MAILER' => 'array',
        'VNPAY_REFUND_ENABLED' => 'true',
        'VNPAY_TMN_CODE' => 'TESTCODE',
        'VNPAY_HASH_SECRET' => 'refund-concurrency-secret',
    ];

    foreach (['host', 'port'] as $key) {
        if (! empty($connection[$key])) {
            $env['DB_'.strtoupper($key)] = (string) $connection[$key];
        }
    }

    return $env;
}

function refundConcurrencyBooking(object $ctx): Booking
{
    return app(CreateBookingAction::class)->handle(new CreateBookingData(
        customerId: $ctx->customer->id,
        hotelId: $ctx->hotel->id,
        roomQuantities: [$ctx->room->id => 1],
        checkIn: Carbon::today()->addDays(7)->toDateString(),
        checkOut: Carbon::today()->addDays(9)->toDateString(),
    ));
}

test('two concurrent cancels create one obligation and never over-refund', function (): void {
    $booking = refundConcurrencyBooking($this);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'amount' => (float) $booking->total_price,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'refund-conc-cancel-1',
        'status' => PaymentStatus::COMPLETED->value,
        'payload' => [
            'vnp_transaction_no' => '15713780',
            'vnp_create_date' => '20261024101010',
        ],
    ]);

    $worker = base_path('tests/Integration/refund_cancel_worker.php');
    $startAt = (string) (microtime(true) + 2.0);
    $env = refundConcurrencyEnv();

    $makeProcess = fn (): Process => new Process([
        PHP_BINARY,
        $worker,
        (string) $booking->id,
        $startAt,
    ], base_path(), $env, null, 60);

    $first = $makeProcess();
    $second = $makeProcess();

    $first->start();
    $second->start();
    $first->wait();
    $second->wait();

    $outputs = $first->getOutput().$second->getOutput();
    $errors = $first->getErrorOutput().$second->getErrorOutput();

    $refunds = PaymentRefund::all();

    expect($refunds)->toHaveCount(1, "workers failed:\n".$outputs."\n".$errors)
        ->and($refunds[0]->idempotency_key)->toBe('hotel-cancel:'.$booking->id.':'.$payment->id)
        ->and($refunds[0]->status)->toBe('pending')
        ->and((float) $refunds[0]->amount)->toBe((float) $payment->amount)
        ->and((float) $payment->fresh()->refunded_amount)->toBe(0.0)
        ->and($booking->fresh()->status)->toBe(BookingStatus::CANCELLED->value);
});

test('two concurrent workers send only one provider request and credit once', function (): void {
    config([
        'services.vnpay.refund_enabled' => true,
        'services.vnpay.tmn_code' => 'TESTCODE',
        'services.vnpay.hash_secret' => 'refund-concurrency-secret',
        'services.vnpay.refund_max_attempts' => 5,
    ]);
    Queue::fake();

    $booking = refundConcurrencyBooking($this);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'amount' => 300000,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'external_id' => 'refund-conc-claim-1',
        'status' => PaymentStatus::COMPLETED->value,
        'payload' => [
            'vnp_transaction_no' => '15713781',
            'vnp_create_date' => '20261024101010',
        ],
    ]);

    $refund = app(RefundService::class)->request($payment, 100000, 'cancel', 'refund-conc:1:'.$payment->id);

    expect($refund)->not->toBeNull();

    $countFile = tempnam(sys_get_temp_dir(), 'refund-claim-');
    $worker = base_path('tests/Integration/refund_claim_worker.php');
    $startAt = (string) (microtime(true) + 2.0);
    $env = refundConcurrencyEnv();

    $makeProcess = fn (): Process => new Process([
        PHP_BINARY,
        $worker,
        (string) $refund?->id,
        $startAt,
        $countFile,
    ], base_path(), $env, null, 60);

    $first = $makeProcess();
    $second = $makeProcess();

    $first->start();
    $second->start();
    $first->wait();
    $second->wait();

    $requests = array_values(array_filter(
        array_map('trim', file($countFile) ?: []),
        static fn (string $line): bool => $line !== '',
    ));

    @unlink($countFile);

    expect($requests)->toHaveCount(1, 'workers failed: '.$first->getErrorOutput().' '.$second->getErrorOutput())
        ->and($refund?->fresh()->status)->toBe('processing')
        ->and($refund?->fresh()->vnp_response_code)->toBe('00')
        ->and((float) $payment->fresh()->refunded_amount)->toBe(100000.0);
});
