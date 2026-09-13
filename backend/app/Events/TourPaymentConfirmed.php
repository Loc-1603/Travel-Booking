<?php

namespace App\Events;

use App\Models\TourPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TourPaymentConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public TourPayment $payment
    ) {}
}
