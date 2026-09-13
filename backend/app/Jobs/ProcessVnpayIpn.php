<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\PaymentService;
use App\Services\VnpayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

    public function handle(PaymentService $paymentService, VnpayService $vnpay): void
    {
        $webhookEvent = WebhookEvent::where('provider', 'vnpay')
            ->where('event_id', $this->eventId)
            ->first();

        if (! $webhookEvent) {
            Log::warning('ProcessVnpayIpn: webhook event not found', ['event_id' => $this->eventId]);
            return;
        }

        if ($webhookEvent->processed_at !== null) {
            return;
        }

        $payment = Payment::find($this->paymentId);
        if (! $payment) {
            Log::warning('ProcessVnpayIpn: payment not found', ['payment_id' => $this->paymentId]);
            $webhookEvent->update(['processed_at' => now()]);
            return;
        }

        // Double-check signature inside the job (defense in depth).
        if (! $vnpay->verifySignature($this->payload)) {
            Log::warning('ProcessVnpayIpn: invalid signature', ['payment_id' => $payment->id]);
            $webhookEvent->update(['processed_at' => now()]);
            return;
        }

        $responseCode = (string) ($this->payload['vnp_ResponseCode'] ?? '');
        $transactionStatus = (string) ($this->payload['vnp_TransactionStatus'] ?? '');

        if ($responseCode === '00' && $transactionStatus === '00') {
            $vnpay->recordTransactionNo($payment, $this->payload);
            $paymentService->confirmPayment($payment->fresh());
        } else {
            $paymentService->markFailed(
                $payment->fresh(),
                'VNPay response: '.$responseCode.' / transaction: '.$transactionStatus
            );
        }

        $webhookEvent->update(['processed_at' => now()]);
    }
}
