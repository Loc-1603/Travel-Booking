<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tour 1vs1 settings
    |--------------------------------------------------------------------------
    |
    | Pricing: total = base_fixed + unit_price * duration + transport_fee
    |   - discount (coupon) - applied before tax, tax logic mirrors booking.php
    | Auth: Tour requires login (no guest-booking unlike Hotel).
    | Chat: socket via Reverb (Phase 5) + REST fallback.
    |
    */

    'hold_ttl_minutes' => (int) env('TOUR_HOLD_TTL_MINUTES', 15),

    'max_hours' => (int) env('TOUR_MAX_HOURS', 12),
    'min_hours' => (int) env('TOUR_MIN_HOURS', 1),
    'max_days' => (int) env('TOUR_MAX_DAYS', 30),
    'min_days' => (int) env('TOUR_MIN_DAYS', 1),

    // Volume discount (replaces Hotel 7-night 5% rule)
    'long_hour_threshold' => (int) env('TOUR_LONG_HOUR_THRESHOLD', 8),
    'long_hour_discount' => (float) env('TOUR_LONG_HOUR_DISCOUNT', 0.05),
    'long_day_threshold' => (int) env('TOUR_LONG_DAY_THRESHOLD', 3),
    'long_day_discount' => (float) env('TOUR_LONG_DAY_DISCOUNT', 0.05),

    // Commission reuses booking rate unless overridden
    'commission_rate' => (float) env('TOUR_COMMISSION_RATE', env('BOOKING_COMMISSION_RATE', 0.10)),

    'currency' => env('TOUR_CURRENCY', 'VND'),
];
