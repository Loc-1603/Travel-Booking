<?php

use App\Models\Coupon;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Services\CouponService;
use Mockery;

beforeEach(function (): void {
    $this->repo = Mockery::mock(CouponRepositoryInterface::class);
    $this->service = new CouponService($this->repo);
});

afterEach(function (): void {
    Mockery::close();
});

test('findActiveByCode delegates to repository in read mode', function (): void {
    $coupon = new Coupon(['code' => 'SAVE10', 'is_active' => true]);
    $this->repo->shouldReceive('findActiveByCode')->once()->andReturn($coupon);

    expect($this->service->findActiveByCode('save10'))->toBe($coupon);
});

test('findActiveByCodeForUpdate delegates to repository', function (): void {
    $coupon = new Coupon(['code' => 'SAVE10', 'is_active' => true]);
    $this->repo->shouldReceive('findActiveByCodeForUpdate')->once()->andReturn($coupon);

    expect($this->service->findActiveByCodeForUpdate('save10'))->toBe($coupon);
});

test('passesUsageLimits checks total and per-user capacity', function (): void {
    $coupon = new Coupon(['usage_limit_total' => 2, 'usage_limit_per_user' => 1]);

    $this->repo->shouldReceive('redemptionCount')->andReturnValues([1, 2, 0]);
    $this->repo->shouldReceive('redemptionCountForUser')->andReturnValues([0, 1]);
    expect($this->service->passesUsageLimits($coupon, 5))->toBeTrue();
    expect($this->service->passesUsageLimits($coupon, 5))->toBeFalse();

    $coupon2 = new Coupon(['usage_limit_total' => null, 'usage_limit_per_user' => 1]);
    expect($this->service->passesUsageLimits($coupon2, 5))->toBeFalse();

    $coupon3 = new Coupon(['usage_limit_total' => 2, 'usage_limit_per_user' => 1]);
    expect($this->service->passesUsageLimits($coupon3, null))->toBeTrue();
});

test('passesUsageLimits uses locking reads when requested', function (): void {
    $coupon = new Coupon(['usage_limit_total' => 2, 'usage_limit_per_user' => 1]);

    $this->repo->shouldReceive('redemptionCount')->once()->with($coupon, true)->andReturn(0);
    $this->repo->shouldReceive('redemptionCountForUser')->once()->with($coupon, 5, true)->andReturn(0);

    expect($this->service->passesUsageLimits($coupon, 5, true))->toBeTrue();
});

test('validateAndGetDiscount returns zero for unknown coupon', function (): void {
    $this->repo->shouldReceive('findActiveByCode')->andReturn(null);

    [$discount, $coupon] = $this->service->validateAndGetDiscount(
        'NOPE', 1, [1 => 1], 1000.0, '2026-11-01', '2026-11-03', 2, 5
    );

    expect($discount)->toBe(0.0)->and($coupon)->toBeNull();
});
test('validateAndGetDiscount enforces hotel eligibility and usage limits', function (): void {
    $coupon = new Coupon([
        'code' => 'SAVE10',
        'type' => Coupon::TYPE_PERCENTAGE,
        'value' => 10,
        'is_active' => true,
        'min_nights' => 3,
        'min_amount' => 500,
        'hotel_ids' => [2],
        'room_ids' => [2],
        'valid_from' => '2026-11-01',
        'valid_to' => '2026-11-10',
        'usage_limit_total' => 1,
        'usage_limit_per_user' => 1,
    ]);

    $this->repo->shouldReceive('findActiveByCode')->andReturn($coupon);
    $this->repo->shouldReceive('redemptionCount')->andReturn(1);

    [$d, $c] = $this->service->validateAndGetDiscount('SAVE10', 2, [1 => 1], 1000.0, '2026-11-01', '2026-11-03', 2, 5);
    expect($d)->toBe(0.0)->and($c)->toBeNull();

    $coupon->min_nights = null;
    [$d, $c] = $this->service->validateAndGetDiscount('SAVE10', 1, [1 => 1], 1000.0, '2026-11-01', '2026-11-03', 2, 5);
    expect($d)->toBe(0.0)->and($c)->toBeNull();

    $coupon->hotel_ids = [1];
    [$d, $c] = $this->service->validateAndGetDiscount('SAVE10', 1, [1 => 1], 1000.0, '2026-11-01', '2026-11-03', 2, 5);
    expect($d)->toBe(0.0)->and($c)->toBeNull();

    $coupon->room_ids = [1];
    [$d, $c] = $this->service->validateAndGetDiscount('SAVE10', 1, [1 => 1], 1000.0, '2026-11-01', '2026-11-03', 2, 5);
    expect($d)->toBe(0.0)->and($c)->toBeNull();

    $coupon->min_amount = null;
    [$d, $c] = $this->service->validateAndGetDiscount('SAVE10', 1, [1 => 1], 1000.0, '2026-11-01', '2026-11-03', 2, 5);
    expect($d)->toBe(0.0)->and($c)->toBeNull();
});

test('validateAndGetDiscount applies discount when valid', function (): void {
    $coupon = new Coupon([
        'code' => 'SAVE10',
        'type' => Coupon::TYPE_PERCENTAGE,
        'value' => 10,
        'is_active' => true,
    ]);
    $this->repo->shouldReceive('findActiveByCode')->andReturn($coupon);
    $this->repo->shouldReceive('redemptionCount')->andReturn(0);
    $this->repo->shouldReceive('redemptionCountForUser')->andReturn(0);

    [$discount, $returned] = $this->service->validateAndGetDiscount(
        'SAVE10', 1, [1 => 1], 1000.0, '2026-11-01', '2026-11-03', 2, 5
    );

    expect($discount)->toBe(100.0);
});

test('validateAndGetDiscount locks coupon when requested', function (): void {
    $coupon = new Coupon(['code' => 'SAVE10', 'type' => Coupon::TYPE_FIXED, 'value' => 50, 'is_active' => true]);
    $this->repo->shouldReceive('findActiveByCodeForUpdate')->once()->andReturn($coupon);
    $this->repo->shouldReceive('redemptionCount')->andReturn(0);

    [$discount, $returned] = $this->service->validateAndGetDiscount(
        'SAVE10', 1, [1 => 1], 1000.0, '2026-11-01', '2026-11-03', 2, 5, true
    );

    expect($discount)->toBe(50.0);
});

test('validateAndGetTourDiscount validates date amount and usage', function (): void {
    $coupon = new Coupon([
        'code' => 'TOUR10',
        'type' => Coupon::TYPE_FIXED,
        'value' => 100,
        'is_active' => true,
        'min_amount' => 500,
        'valid_from' => '2026-11-01',
        'valid_to' => '2026-11-10',
        'usage_limit_total' => 1,
    ]);
    $this->repo->shouldReceive('findActiveByCode')->andReturn($coupon);
    $this->repo->shouldReceive('redemptionCount')->andReturnValues([1, 0]);

    [$d, $c] = $this->service->validateAndGetTourDiscount('TOUR10', 1000.0, '2026-10-31', 5);
    expect($d)->toBe(0.0)->and($c)->toBeNull();

    [$d, $c] = $this->service->validateAndGetTourDiscount('TOUR10', 100.0, '2026-11-02', 5);
    expect($d)->toBe(0.0)->and($c)->toBeNull();

    [$d, $c] = $this->service->validateAndGetTourDiscount('TOUR10', 1000.0, '2026-11-02', 5);
    expect($d)->toBe(0.0)->and($c)->toBeNull();

    [$d, $c] = $this->service->validateAndGetTourDiscount('TOUR10', 1000.0, '2026-11-02', 5);
    expect($d)->toBe(100.0)->and($c)->toBe($coupon);
});

test('computeDiscount handles percentage and fixed', function (): void {
    $percentage = new Coupon(['type' => Coupon::TYPE_PERCENTAGE, 'value' => 10]);
    $fixed = new Coupon(['type' => Coupon::TYPE_FIXED, 'value' => 50]);

    expect($this->service->computeDiscount($percentage, 1000.0))->toBe(100.0)
        ->and($this->service->computeDiscount($fixed, 1000.0))->toBe(50.0)
        ->and($this->service->computeDiscount($fixed, 10.0))->toBe(10.0);
});
