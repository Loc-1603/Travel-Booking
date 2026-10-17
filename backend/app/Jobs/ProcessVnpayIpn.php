<?php

namespace App\Jobs;

use App\Actions\ConfirmVnpayPaymentAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job for confirming a hotel VNPay IPN (Phase 4 / J-1).
 *
 * Thin wrapper around ConfirmVnpayPaymentAction: all decision logic
 * (webhook-event lookup, processed-at guard, signature defense-in-depth,
 * 00/00 confirmation vs failure, transaction-no recording) lives in the
 * Action; this job only holds the serialized request payload.
 */
class ProcessVnpayIpn implements ShouldQueue
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

    public function handle(ConfirmVnpayPaymentAction $action): void
    {
        $action->handle($this->eventId, $this->paymentId, $this->payload);
    }
}
