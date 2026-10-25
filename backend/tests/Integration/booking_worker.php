<?php

use App\Actions\CreateBookingAction;
use App\DTOs\CreateBookingData;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$customerId, $hotelId, $roomId, $checkIn, $checkOut, $couponCode, $startAt] = array_slice($argv, 1);

$waitUntil = (float) $startAt;
while (microtime(true) < $waitUntil) {
    usleep(500);
}

try {
    $booking = $app->make(CreateBookingAction::class)->handle(new CreateBookingData(
        customerId: (int) $customerId,
        hotelId: (int) $hotelId,
        roomQuantities: [(int) $roomId => 1],
        checkIn: $checkIn,
        checkOut: $checkOut,
        couponCode: $couponCode,
    ));

    fwrite(STDOUT, json_encode([
        'id' => $booking->id,
        'coupon_id' => $booking->coupon_id,
        'discount_amount' => (float) $booking->discount_amount,
    ]));

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage());
    exit(1);
}
