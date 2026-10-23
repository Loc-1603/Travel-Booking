<?php

namespace App\Actions;

use App\Enums\TourBookingStatus;
use App\Models\TourBooking;
use App\Models\TourDispute;
use App\Models\User;
use App\Repositories\Contracts\TourDisputeRepositoryInterface;
use RuntimeException;

/**
 * Standard formula for opening a tour dispute (Phase 2 / A-6).
 *
 * Status/dispute preconditions are enforced by the controller so it can
 * map them to the existing INVALID_STATUS / DISPUTE_EXISTS API codes.
 * The database unique index on tour_booking_id remains the final guard.
 */
class OpenTourDisputeAction
{
    public function __construct(
        protected TourDisputeRepositoryInterface $disputes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(TourBooking $booking, User $user, array $data = []): TourDispute
    {
        if ($booking->status === TourBookingStatus::PENDING_PAYMENT->value) {
            throw new RuntimeException('Disputes are available after payment is completed.');
        }

        return $this->disputes->create([
            'tour_booking_id' => $booking->id,
            'status' => 'open',
            'contact_name' => ($data['contact_name'] ?? null) ?: $user->name,
            'contact_email' => ($data['contact_email'] ?? null) ?: $user->email,
            'contact_phone' => $data['contact_phone'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
        ]);
    }
}
