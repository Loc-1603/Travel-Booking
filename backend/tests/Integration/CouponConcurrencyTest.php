<?php

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
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
        'name' => 'Concurrency Vendor', 'email' => 'conc-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Concurrency Customer', 'email' => 'conc-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $this->vendor->id, 'name' => 'Concurrency Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam', 'status' => 'active',
        'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Concurrency Room',
        'capacity' => 2, 'base_price' => 1000000, 'total_rooms' => 5,
    ]);
    $this->coupon = Coupon::create([
        'code' => 'CONCCOUPON', 'type' => 'percentage', 'value' => 10,
        'is_active' => true, 'usage_limit_total' => 1, 'usage_limit_per_user' => 1,
    ]);
});

test('coupon row lock blocks a competing transaction', function (): void {
    $driver = DB::getDriverName();
    config(['database.connections.lock_probe' => config('database.connections.'.$driver)]);
    DB::purge('lock_probe');

    $locker = DB::connection();
    $probe = DB::connection('lock_probe');

    $locker->beginTransaction();
    $probe->beginTransaction();

    try {
        $locker->table('coupons')->where('id', $this->coupon->id)->lockForUpdate()->first();

        $probe->statement($driver === 'pgsql'
            ? "SET LOCAL lock_timeout = '1s'"
            : 'SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            $probe->table('coupons')->where('id', $this->coupon->id)->lockForUpdate()->first();
        } catch (Illuminate\Database\QueryException) {
            $blocked = true;
        }
    } finally {
        if ($probe->transactionLevel() > 0) {
            $probe->rollBack();
        }
        if ($locker->transactionLevel() > 0) {
            $locker->rollBack();
        }
    }

    expect($blocked)->toBeTrue();
});

test('two concurrent bookings cannot exceed the coupon total or per-user limit', function (string $code, int $totalLimit, int $perUserLimit): void {
    $this->coupon->update([
        'code' => $code,
        'usage_limit_total' => $totalLimit,
        'usage_limit_per_user' => $perUserLimit,
    ]);

    $driver = DB::getDriverName();
    $connection = config('database.connections.'.$driver);

    $env = [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'DB_CONNECTION' => $driver,
        'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
    ];
    foreach (['host', 'port'] as $key) {
        if (! empty($connection[$key])) {
            $env['DB_'.strtoupper($key)] = (string) $connection[$key];
        }
    }

    $worker = base_path('tests/Integration/booking_worker.php');
    $startAt = (string) (microtime(true) + 2.0);

    $makeProcess = fn (string $checkIn, string $checkOut): Process => new Process([
        PHP_BINARY,
        $worker,
        (string) $this->customer->id,
        (string) $this->hotel->id,
        (string) $this->room->id,
        $checkIn,
        $checkOut,
        $code,
        $startAt,
    ], base_path(), $env, null, 60);

    $first = $makeProcess(
        now()->addDays(30)->toDateString(),
        now()->addDays(32)->toDateString(),
    );
    $second = $makeProcess(
        now()->addDays(40)->toDateString(),
        now()->addDays(42)->toDateString(),
    );

    $first->start();
    $second->start();
    $first->wait();
    $second->wait();

    if ($first->getExitCode() !== 0 || $second->getExitCode() !== 0) {
        $this->fail(sprintf(
            "Booking workers failed.\nFirst (%s): %s\nSecond (%s): %s",
            $first->getExitCode(),
            $first->getErrorOutput(),
            $second->getExitCode(),
            $second->getErrorOutput(),
        ));
    }

    $results = [
        json_decode($first->getOutput(), true, 512, JSON_THROW_ON_ERROR),
        json_decode($second->getOutput(), true, 512, JSON_THROW_ON_ERROR),
    ];

    $winners = count(array_filter(
        $results,
        fn (array $result): bool => (int) $result['coupon_id'] === $this->coupon->id,
    ));

    expect($winners)->toBe(1)
        ->and(Booking::query()->count())->toBe(2)
        ->and(Booking::query()->where('coupon_id', $this->coupon->id)->count())->toBe(1);
})->with([
    'total limit' => ['CONCCOUPON', 1, 1],
    'per-user limit' => ['PERUSERCONC', 10, 1],
]);
