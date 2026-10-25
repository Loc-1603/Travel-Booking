<?php

use App\Actions\CancelBookingAction;
use App\Models\Booking;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$bookingId, $startAt] = array_slice($argv, 1);

$waitUntil = (float) $startAt;
while (microtime(true) < $waitUntil) {
    usleep(500);
}

try {
    $booking = Booking::with('payments')->findOrFail((int) $bookingId);
    $result = $app->make(CancelBookingAction::class)->handle($booking);

    fwrite(STDOUT, json_encode([
        'id' => $result->id,
        'status' => $result->status,
    ]));

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage());
    exit(1);
}
