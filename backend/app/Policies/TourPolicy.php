<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\TourProduct;
use App\Models\User;

class TourPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TourProduct $tour): bool
    {
        if (in_array($user->role, [Role::SUPER_ADMIN, Role::ADMIN], true)) {
            return true;
        }

        if ($user->role === Role::VENDOR) {
            return (int) $tour->provider?->vendor_id === (int) $user->id;
        }

        return $tour->status === 'published';
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::SUPER_ADMIN, Role::ADMIN, Role::VENDOR], true);
    }

    public function update(User $user, TourProduct $tour): bool
    {
        if (in_array($user->role, [Role::SUPER_ADMIN, Role::ADMIN], true)) {
            return true;
        }

        return $user->role === Role::VENDOR
            && (int) $tour->provider?->vendor_id === (int) $user->id;
    }
}
