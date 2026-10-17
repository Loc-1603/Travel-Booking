<?php

use App\DTOs\CheckoutData;
use App\DTOs\CreateBookingData;

test('create booking data carries slice inputs with sane defaults', function (): void {
    $data = new CreateBookingData(
        customerId: 7,
        hotelId: 3,
        roomQuantities: [11 => 2, 12 => 1],
        checkIn: '2026-11-01',
        checkOut: '2026-11-03',
    );

    expect($data->customerId)->toBe(7)
        ->and($data->hotelId)->toBe(3)
        ->and($data->roomQuantities)->toBe([11 => 2, 12 => 1])
        ->and($data->checkIn)->toBe('2026-11-01')
        ->and($data->checkOut)->toBe('2026-11-03')
        ->and($data->currency)->toBe('VND')
        ->and($data->couponCode)->toBeNull()
        ->and($data->guestEmail)->toBeNull()
        ->and($data->guestName)->toBeNull()
        ->and($data->lateCheckout)->toBeFalse();
});

test('create booking data supports guest checkout fields', function (): void {
    $data = new CreateBookingData(
        customerId: null,
        hotelId: 3,
        roomQuantities: [11 => 1],
        checkIn: '2026-11-01',
        checkOut: '2026-11-02',
        currency: 'VND',
        couponCode: 'SALE10',
        guestEmail: 'guest@example.com',
        guestName: 'Guest',
        lateCheckout: true,
    );

    expect($data->customerId)->toBeNull()
        ->and($data->couponCode)->toBe('SALE10')
        ->and($data->guestEmail)->toBe('guest@example.com')
        ->and($data->guestName)->toBe('Guest')
        ->and($data->lateCheckout)->toBeTrue();
});

test('booking dtos are immutable', function (): void {
    $booking = new CreateBookingData(
        customerId: 7,
        hotelId: 3,
        roomQuantities: [11 => 1],
        checkIn: '2026-11-01',
        checkOut: '2026-11-02',
    );

    expect(fn () => $booking->hotelId = 99)->toThrow(Error::class);
});

test('checkout data carries booking id with optional network hints', function (): void {
    $defaults = new CheckoutData(bookingId: 42);

    expect($defaults->bookingId)->toBe(42)
        ->and($defaults->ipAddr)->toBeNull()
        ->and($defaults->bankCode)->toBeNull();

    $full = new CheckoutData(bookingId: 42, ipAddr: '127.0.0.1', bankCode: 'NCB');

    expect($full->ipAddr)->toBe('127.0.0.1')
        ->and($full->bankCode)->toBe('NCB');

    expect(fn () => $full->bookingId = 1)->toThrow(Error::class);
});
