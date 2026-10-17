<?php

namespace App\Actions;

use App\Models\Booking;

/**
 * Claim a guest booking: attach to the current user (Phase 2 / A-3).
 *
 * Business rules:
 * - Booking must be a guest booking (customer_id is null).
 * - guest_email must be present.
 * - guest_email must match the authenticated user's email (case-insensitive).
 *
 * On success: sets customer_id, clears guest_email/guest_name.
 */
class ClaimBookingAction
{
    public function handle(Booking $booking, int $userId, string $userEmail): Booking
    {
        if ($booking->customer_id !== null) {
            throw new \DomainException('This booking is already linked to an account.');
        }
        if (empty($booking->guest_email)) {
            throw new \DomainException('This booking cannot be claimed.');
        }
        if (strtolower((string) $booking->guest_email) !== strtolower($userEmail)) {
            throw new \DomainException('You can only claim bookings made with your email address.');
        }

        $booking->update([
            'customer_id' => $userId,
            'guest_email' => null,
            'guest_name' => null,
        ]);

        return $booking->fresh()->load(['hotel', 'bookingRooms.room', 'coupon', 'dispute']);
    }
}
