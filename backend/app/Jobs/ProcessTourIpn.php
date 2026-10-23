<?php

namespace App\Jobs;

use App\Actions\ConfirmTourVnpayPaymentAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job for confirming a tour VNPay IPN (Phase 2 / A-5, thin per J-1).
 *
 * Thin wrapper around ConfirmTourVnpayPaymentAction: all decision logic
 * lives in the Action; this job only holds the serialized request payload.
 */
class ProcessTourIpn implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $eventId,
        public int $paymentId,
        public array $payload
    ) {}

    public function handle(ConfirmTourVnpayPaymentAction $action): void
    {
        $action->handle($this->eventId, $this->paymentId, $this->payload);
    }
}
