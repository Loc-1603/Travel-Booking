<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\BookingRoomRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\HotelRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\RoomAvailabilityRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function (): void {
    $vendor = User::create([
        'name' => 'Repo Vendor', 'email' => 'repo-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $customer = User::create([
        'name' => 'Repo Customer', 'email' => 'repo-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->hotel = Hotel::create([
        'vendor_id' => $vendor->id, 'name' => 'Repo Hotel',
        'city' => 'Hanoi', 'country' => 'Vietnam',
        'status' => 'active', 'tax_rate' => 0.08, 'tax_name' => 'VAT',
    ]);
    $this->room = Room::create([
        'hotel_id' => $this->hotel->id, 'name' => 'Repo Room',
        'capacity' => 2, 'base_price' => 500000, 'total_rooms' => 5,
    ]);
    $this->customer = $customer;
});

test('booking repository creates and finds bookings', function (): void {
    $repos = app(BookingRepositoryInterface::class);

    $booking = $repos->create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND',
    ]);

    expect($booking->uuid)->not->toBeEmpty()
        ->and($repos->findById($booking->id)?->id)->toBe($booking->id)
        ->and($repos->findByUuid($booking->uuid)?->id)->toBe($booking->id)
        ->and($repos->findByUuidOrFail($booking->uuid)->id)->toBe($booking->id);

    $updated = $repos->update($booking, ['status' => BookingStatus::CONFIRMED->value]);
    expect($updated->status)->toBe(BookingStatus::CONFIRMED->value);

    $with = $repos->findByUuid($booking->uuid, ['hotel', 'bookingRooms']);
    expect($with->hotel->id)->toBe($this->hotel->id);

    $page = $repos->paginateByCustomer($this->customer->id, ['hotel'], 15);
    expect($page->total())->toBe(1);
});

test('booking room repository creates rows', function (): void {
    $bookings = app(BookingRepositoryInterface::class);
    $rooms = app(BookingRoomRepositoryInterface::class);

    $booking = $bookings->create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND',
    ]);

    $row = $rooms->create([
        'booking_id' => $booking->id, 'room_id' => $this->room->id,
        'quantity' => 2, 'unit_price' => 500000,
    ]);

    expect($row->booking_id)->toBe($booking->id)
        ->and($row->room_id)->toBe($this->room->id);
});

test('room and availability repositories cover slice queries', function (): void {
    $rooms = app(RoomRepositoryInterface::class);
    $avail = app(RoomAvailabilityRepositoryInterface::class);

    expect($rooms->findById($this->room->id)?->id)->toBe($this->room->id)
        ->and($rooms->findOrFail($this->room->id)->id)->toBe($this->room->id)
        ->and($rooms->findInHotel($this->room->id, $this->hotel->id)?->id)->toBe($this->room->id)
        ->and($rooms->findInHotel($this->room->id, 999999))->toBeNull()
        ->and($rooms->totalRoomsById()[$this->room->id])->toBe(5);

    $date = Carbon::today()->addDays(7)->format('Y-m-d');
    expect($avail->findForDate($this->room->id, $date))->toBeNull();

    $created = $avail->firstOrCreateForDate($this->room->id, $date, ['available_rooms' => 5]);
    expect($created->available_rooms)->toBe(5)
        ->and($avail->findForDate($this->room->id, $date)?->id)->toBe($created->id)
        ->and($avail->findForDateLocked($this->room->id, $date)?->id)->toBe($created->id);

    expect($avail->listForRange($this->room->id, $date, $date)->count())->toBe(1)
        ->and($avail->listForDateRange($date, $date)->count())->toBeGreaterThanOrEqual(1);

    $locked = $avail->findForDateLocked($this->room->id, $date);
    $locked->decrement('available_rooms', 2);
    expect($avail->findForDate($this->room->id, $date)->available_rooms)->toBe(3);

    $avail->incrementAvailable($this->room->id, $date, 2);
    expect($avail->findForDate($this->room->id, $date)->available_rooms)->toBe(5);
});

test('hotel and coupon repositories cover slice queries', function (): void {
    $hotels = app(HotelRepositoryInterface::class);
    $coupons = app(CouponRepositoryInterface::class);

    expect($hotels->findById($this->hotel->id, ['countryRelation'])?->id)->toBe($this->hotel->id);

    $coupon = \App\Models\Coupon::create([
        'code' => 'REPO10', 'type' => 'percentage', 'value' => 10, 'is_active' => true,
    ]);

    expect($coupons->findActiveByCode('repo10')?->id)->toBe($coupon->id)
        ->and($coupons->findActiveByCode('missing'))->toBeNull()
        ->and($coupons->redemptionCount($coupon))->toBe(0)
        ->and($coupons->redemptionCountForUser($coupon, $this->customer->id))->toBe(0);

    Booking::create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND', 'coupon_id' => $coupon->id,
    ]);

    expect($coupons->redemptionCount($coupon))->toBe(1)
        ->and($coupons->redemptionCountForUser($coupon, $this->customer->id))->toBe(1);
});

test('payment repository covers slice queries', function (): void {
    $bookings = app(BookingRepositoryInterface::class);
    $payments = app(PaymentRepositoryInterface::class);

    $booking = $bookings->create([
        'customer_id' => $this->customer->id, 'hotel_id' => $this->hotel->id,
        'status' => BookingStatus::PENDING_PAYMENT->value,
        'check_in' => Carbon::today()->addDays(7)->toDateString(),
        'check_out' => Carbon::today()->addDays(9)->toDateString(),
        'total_price' => 1000000, 'currency' => 'VND',
    ]);

    $payment = $payments->create([
        'booking_id' => $booking->id, 'amount' => 1000000, 'currency' => 'VND',
        'provider' => 'vnpay', 'external_id' => 'repo-txn-1',
        'status' => PaymentStatus::PENDING->value,
    ]);

    expect($payments->findById($payment->id)?->id)->toBe($payment->id)
        ->and($payments->findByIdForUpdate($payment->id)?->id)->toBe($payment->id)
        ->and($payments->findByTxnRef('repo-txn-1')?->id)->toBe($payment->id);

    $updated = $payments->update($payment, ['status' => PaymentStatus::COMPLETED->value]);
    expect($updated->status)->toBe(PaymentStatus::COMPLETED->value);
});

test('webhook event repository enforces claim idempotency', function (): void {
    $events = app(WebhookEventRepositoryInterface::class);

    expect($events->find('vnpay', 'repo-event-1'))->toBeNull();

    $created = $events->create(['provider' => 'vnpay', 'event_id' => 'repo-event-1']);
    expect($created->event_id)->toBe('repo-event-1')
        ->and($events->find('vnpay', 'repo-event-1')?->id)->toBe($created->id);

    $updated = $events->update($created, ['processed_at' => now()]);
    expect($updated->processed_at)->not->toBeNull();

    expect(fn () => $events->create(['provider' => 'vnpay', 'event_id' => 'repo-event-1']))
        ->toThrow(UniqueConstraintViolationException::class);
});

