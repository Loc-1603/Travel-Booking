<?php

namespace App\Actions;

use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;
use App\Services\TourPaymentService;
use App\Services\TourVnpayAdapter;
use Illuminate\Support\Facades\Log;

/**
 * Standard formula for confirming a tour VNPay IPN (Phase 2 / A-5).
 */
class ConfirmTourVnpayPaymentAction
{
    public function __construct(
        protected WebhookEventRepositoryInterface $events,
        protected TourPaymentRepositoryInterface $payments,
        protected TourPaymentService $paymentService,
        protected TourVnpayAdapter $vnpay,
    ) {}

    public function handle(string $eventId, int $paymentId, array $payload): bool
    {
        $webhookEvent = $this->events->find('vnpay', $eventId);
        if (! $webhookEvent) {
            Log::warning('ConfirmTourVnpayPayment: webhook event not found', ['event_id' => $eventId]);

            return false;
        }

        if ($webhookEvent->processed_at !== null) {
            return false;
        }

        $payment = $this->payments->findById($paymentId);
        if (! $payment) {
            Log::warning('ConfirmTourVnpayPayment: payment not found', ['payment_id' => $paymentId]);
            $this->events->update($webhookEvent, ['processed_at' => now()]);

            return false;
        }

        if (! $this->vnpay->verifySignature($payload)) {
            Log::warning('ConfirmTourVnpayPayment: invalid signature', ['payment_id' => $payment->id]);
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
}
