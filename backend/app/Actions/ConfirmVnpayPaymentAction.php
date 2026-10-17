<?php

namespace App\Actions;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;
use App\Services\PaymentService;
use App\Services\VnpayService;
use Illuminate\Support\Facades\Log;

/**
 * Standard formula for confirming a hotel VNPay IPN (Phase 2 / A-4).
 *
 * Owns the ProcessVnpayIpn decision tree so the job becomes thin in J-1:
 * load event -> skip processed -> load payment -> verify signature again
 * (defense in depth) -> 00/00 ? record txn + confirm : mark failed ->
 * stamp processed_at. Returns false when there is nothing to process.
 */
class ConfirmVnpayPaymentAction
{
    public function __construct(
        protected WebhookEventRepositoryInterface $events,
        protected PaymentRepositoryInterface $payments,
        protected PaymentService $paymentService,
        protected VnpayService $vnpay,
    ) {}

    public function handle(string $eventId, int $paymentId, array $payload): bool
    {
        $webhookEvent = $this->events->find('vnpay', $eventId);
        if (! $webhookEvent) {
            Log::warning('ConfirmVnpayPayment: webhook event not found', ['event_id' => $eventId]);

            return false;
        }

        if ($webhookEvent->processed_at !== null) {
            return false;
        }

        $payment = $this->payments->findById($paymentId);
        if (! $payment) {
            Log::warning('ConfirmVnpayPayment: payment not found', ['payment_id' => $paymentId]);
            $this->events->update($webhookEvent, ['processed_at' => now()]);

            return false;
        }

        if (! $this->vnpay->verifySignature($payload)) {
            Log::warning('ConfirmVnpayPayment: invalid signature', ['payment_id' => $payment->id]);
            $this->events->update($webhookEvent, ['processed_at' => now()]);

            return false;
        }

        $responseCode = (string) ($payload['vnp_ResponseCode'] ?? '');
        $transactionStatus = (string) ($payload['vnp_TransactionStatus'] ?? '');

        if ($responseCode === '00' && $transactionStatus === '00') {
            $this->vnpay->recordTransactionNo($payment, $payload);
            $this->paymentService->confirmPayment($payment->fresh());
        } else {
            $this->paymentService->markFailed(
                $payment->fresh(),
                'VNPay response: '.$responseCode.' / transaction: '.$transactionStatus
            );
        }

        $this->events->update($webhookEvent, ['processed_at' => now()]);

        return true;
    }

    public function confirmPayment(Payment $payment): void
    {
        $this->paymentService->confirmPayment($payment);
    }

    public function markFailed(Payment $payment, ?string $note = null): void
    {
        $this->paymentService->markFailed($payment, $note);
    }

    public function recordTransactionNo(Payment $payment, array $input): void
    {
        $this->vnpay->recordTransactionNo($payment, $input);
    }
}
