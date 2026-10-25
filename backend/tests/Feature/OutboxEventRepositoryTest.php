<?php

use App\Models\OutboxEvent;
use App\Repositories\Contracts\OutboxEventRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->repo = app(OutboxEventRepositoryInterface::class);
});

test('outbox migration creates the expected table and columns', function (): void {
    expect(Schema::hasTable('outbox_events'))->toBeTrue()
        ->and(Schema::hasColumns('outbox_events', [
            'id', 'event_id', 'event_type', 'event_version', 'payload',
            'available_at', 'published_at', 'attempts', 'locked_at',
            'lease_expires_at', 'last_error', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('outbox repository returns existing row for createIfAbsent', function (): void {
    $first = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:2',
        'event_type' => 'TourPaymentConfirmed',
        'payload' => ['payment_id' => 2],
        'available_at' => now(),
    ]);

    $second = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:2',
        'event_type' => 'TourPaymentConfirmed',
        'payload' => ['payment_id' => 999],
        'available_at' => now()->addHour(),
    ]);

    expect($second->id)->toBe($first->id)
        ->and($second->payload)->toBe(['payment_id' => 2]);
});

test('outbox event casts payload and timestamps', function (): void {
    $event = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:3',
        'event_type' => 'TourPaymentConfirmed',
        'event_version' => 1,
        'payload' => ['payment_id' => 3, 'tour_booking_id' => 5],
        'available_at' => '2026-10-24 10:00:00',
    ]);

    $fresh = $this->repo->findById($event->id);

    expect($fresh->payload)->toBe(['payment_id' => 3, 'tour_booking_id' => 5])
        ->and($fresh->available_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($fresh->event_version)->toBe(1)
        ->and($fresh->attempts)->toBe(0);
});

test('direct duplicate event id violates unique constraint', function (): void {
    OutboxEvent::create([
        'event_id' => 'tour-payment-confirmed:4',
        'event_type' => 'TourPaymentConfirmed',
    ]);

    expect(fn () => OutboxEvent::create([
        'event_id' => 'tour-payment-confirmed:4',
        'event_type' => 'TourPaymentConfirmed',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('outbox claim skips published and active leases, reclaims stale leases', function (): void {
    $now = now();

    $publishable = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:10',
        'event_type' => 'TourPaymentConfirmed',
        'available_at' => $now,
    ]);

    $published = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:11',
        'event_type' => 'TourPaymentConfirmed',
        'available_at' => $now,
    ]);
    $this->repo->markPublished($published);

    $leased = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:12',
        'event_type' => 'TourPaymentConfirmed',
        'available_at' => $now,
    ]);
    $leased->update(['lease_expires_at' => $now->copy()->addMinutes(5)]);

    $stale = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:13',
        'event_type' => 'TourPaymentConfirmed',
        'available_at' => $now,
    ]);
    $stale->update(['lease_expires_at' => $now->copy()->subMinute()]);

    $claimed = $this->repo->claimBatch(10, 90);

    $claimedIds = $claimed->pluck('id')->all();

    expect($claimedIds)->toContain($publishable->id)
        ->and($claimedIds)->toContain($stale->id)
        ->and($claimedIds)->not->toContain($published->id)
        ->and($claimedIds)->not->toContain($leased->id);

    foreach ($claimed as $event) {
        expect($event->lease_expires_at)->not->toBeNull()
            ->and($event->locked_at)->not->toBeNull();
    }
});

test('outbox markPublished, scheduleRetry and markFailed update state', function (): void {
    $event = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:20',
        'event_type' => 'TourPaymentConfirmed',
        'available_at' => now(),
    ]);

    $claimed = $this->repo->claimBatch(1, 90)->first();
    expect($claimed->id)->toBe($event->id);

    $retried = $this->repo->scheduleRetry($claimed, now()->addMinutes(2), 'temporary failure');
    expect($retried->attempts)->toBe(1)
        ->and($retried->last_error)->toBe('temporary failure')
        ->and($retried->locked_at)->toBeNull()
        ->and($retried->lease_expires_at)->toBeNull();

    $failed = $this->repo->markFailed($retried, 'permanent failure');
    expect($failed->attempts)->toBe(2)
        ->and($failed->last_error)->toBe('permanent failure');

    $published = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:21',
        'event_type' => 'TourPaymentConfirmed',
        'available_at' => now(),
    ]);
    $claimedPublished = $this->repo->claimBatch(1, 90)->firstWhere('id', $published->id);
    $marked = $this->repo->markPublished($claimedPublished);
    expect($marked->published_at)->not->toBeNull()
        ->and($marked->locked_at)->toBeNull()
        ->and($marked->lease_expires_at)->toBeNull();
});

test('outbox releaseStaleLeases clears expired leases', function (): void {
    $event = $this->repo->createIfAbsent([
        'event_id' => 'tour-payment-confirmed:30',
        'event_type' => 'TourPaymentConfirmed',
        'available_at' => now(),
    ]);
    $event->update(['lease_expires_at' => now()->subMinute()]);

    $released = $this->repo->releaseStaleLeases();

    expect($released)->toBe(1)
        ->and($event->fresh()->lease_expires_at)->toBeNull();
});
