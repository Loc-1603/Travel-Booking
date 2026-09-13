<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\TourBooking;
use App\Models\User;

class TourBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::SUPER_ADMIN, Role::ADMIN, Role::VENDOR, Role::CUSTOMER], true);
    }

    public function view(User $user, TourBooking $booking): bool
    {
        if (in_array($user->role, [Role::SUPER_ADMIN, Role::ADMIN], true)) {
            return true;
        }

        if ($user->role === Role::VENDOR) {
            return (int) $booking->provider?->vendor_id === (int) $user->id;
        }

        if ($user->role === Role::CUSTOMER) {
            return (int) $booking->customer_id === (int) $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Tour requires login (no guest flow). Vendors/admins may also book for testing.
        return in_array($user->role, [Role::SUPER_ADMIN, Role::ADMIN, Role::VENDOR, Role::CUSTOMER], true);
    }

    public function cancel(User $user, TourBooking $booking): bool
    {
        return $this->view($user, $booking);
    }

    public function dispute(User $user, TourBooking $booking): bool
    {
        if ($user->role !== Role::CUSTOMER) {
            return false;
        }

        return (int) $booking->customer_id === (int) $user->id;
    }
}
