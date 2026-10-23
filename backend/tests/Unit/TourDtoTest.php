<?php

use App\DTOs\CreateGuideTourBookingData;
use App\DTOs\CreateTourBookingData;
use App\DTOs\TourCheckoutData;

test('create tour booking data carries slice inputs with sane defaults', function (): void {
    $data = new CreateTourBookingData(
        customerId: 7,
        tourId: 3,
        slotId: 9,
        pricingMode: 'hour',
        durationValue: 4,
    );

    expect($data->customerId)->toBe(7)
        ->and($data->tourId)->toBe(3)
        ->and($data->slotId)->toBe(9)
        ->and($data->pricingMode)->toBe('hour')
        ->and($data->durationValue)->toBe(4)
        ->and($data->currency)->toBe('VND')
        ->and($data->couponCode)->toBeNull()
        ->and($data->meetingPoint)->toBeNull()
        ->and($data->customerNotes)->toBeNull();
});

test('create guide tour booking data carries slice inputs with sane defaults', function (): void {
    $data = new CreateGuideTourBookingData(
        customerId: 7,
        providerUuid: 'provider-uuid',
    );

    expect($data->customerId)->toBe(7)
        ->and($data->providerUuid)->toBe('provider-uuid')
        ->and($data->slotId)->toBeNull()
        ->and($data->travelDate)->toBeNull()
        ->and($data->currency)->toBe('VND')
        ->and($data->couponCode)->toBeNull();
});

test('tour checkout data carries booking id with optional network hints', function (): void {
    $defaults = new TourCheckoutData(bookingId: 42);

    expect($defaults->bookingId)->toBe(42)
        ->and($defaults->ipAddr)->toBeNull()
        ->and($defaults->bankCode)->toBeNull();

    $full = new TourCheckoutData(bookingId: 42, ipAddr: '127.0.0.1', bankCode: 'NCB');

    expect($full->ipAddr)->toBe('127.0.0.1')
        ->and($full->bankCode)->toBe('NCB');

    expect(fn () => $full->bookingId = 1)->toThrow(Error::class);
});

test('tour booking dtos are immutable', function (): void {
    $booking = new CreateTourBookingData(
        customerId: 7, tourId: 3, slotId: 9, pricingMode: 'hour', durationValue: 4,
    );

    expect(fn () => $booking->tourId = 99)->toThrow(Error::class);
});
