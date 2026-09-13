<?php

namespace App\Jobs;

use App\Models\TourPayment;
use App\Models\WebhookEvent;
use App\Services\TourPaymentService;
use App\Services\TourVnpayAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

    public function handle(TourPaymentService $payments, TourVnpayAdapter $vnpay): void
    {
        $webhookEvent = WebhookEvent::where('provider', 'vnpay')
            ->where('event_id', $this->eventId)
            ->first();

        if (! $webhookEvent) {
            Log::warning('ProcessTourIpn: webhook event not found', ['event_id' => $this->eventId]);
            return;
        }

        if ($webhookEvent->processed_at !== null) {
            return;
        }

        $payment = TourPayment::find($this->paymentId);
        if (! $payment) {
            Log::warning('ProcessTourIpn: payment not found', ['payment_id' => $this->paymentId]);
            $webhookEvent->update(['processed_at' => now()]);
            return;
        }

        if (! $vnpay->verifySignature($this->payload)) {
            Log::warning('ProcessTourIpn: invalid signature', ['payment_id' => $payment->id]);
            $webhookEvent->update(['processed_at' => now()]);
            return;
        }

        $responseCode = (string) ($this->payload['vnp_ResponseCode'] ?? '');
        $transactionStatus = (string) ($this->payload['vnp_TransactionStatus'] ?? '');

        if ($responseCode === '00' && $transactionStatus === '00') {
            $vnpay->recordTransactionNo($payment, $this->payload);
            $payments->confirmPayment($payment->fresh());
        } else {
            $payments->markFailed(
                $payment->fresh(),
                'VNPay response: '.$responseCode.' / transaction: '.$transactionStatus
            );
        }

        $webhookEvent->update(['processed_at' => now()]);
    }
}
