<?php

use App\Enums\Role;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Vendor inbox channel (tour messages)
|--------------------------------------------------------------------------
| Only the vendor who owns the given id may join to receive inbox updates.
*/
Broadcast::channel('vendor.{id}.tour-messages', function ($user, $id) {
    return $user->role === Role::VENDOR && (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Tour 1vs1 booking chat channel (Phase 5)
|--------------------------------------------------------------------------
| Only the booking customer or the provider's vendor may join.
| Tour requires login, so no guest/signed-URL branch is needed.
*/
Broadcast::channel('tour.booking.{uuid}', function ($user, $uuid) {
    $booking = \App\Models\TourBooking::where('uuid', $uuid)->first();
    if (! $booking) {
        return false;
    }

    if ((int) $booking->customer_id === (int) $user->id) {
        return true;
    }

    return $booking->provider
        && (int) $booking->provider->vendor_id === (int) $user->id;
});
