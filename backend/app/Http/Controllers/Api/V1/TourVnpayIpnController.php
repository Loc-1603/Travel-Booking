<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ProcessTourIpn;
use App\Models\WebhookEvent;
use App\Services\TourVnpayAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VNPay IPN for tour_payments. Mirrors VnpayIpnController but queries
 * tour_payments (event_id prefixed tour:) — hotel flow untouched.
 */
class TourVnpayIpnController extends BaseApiController
{
    public function __construct(
        protected TourVnpayAdapter $vnpay,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->query();

        if (! $this->vnpay->verifySignature($input)) {
            Log::warning('Tour VNPay IPN: invalid signature', ['txn_ref' => $input['vnp_TxnRef'] ?? null]);

            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }

        $payment = $this->vnpay->findPaymentForIpn($input);
        if (! $payment) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }

        if ($payment->status === \App\Enums\PaymentStatus::COMPLETED->value) {
            return response()->json(['RspCode' => '02', 'Message' => 'Order already confirmed']);
        }

        if (! $this->vnpay->amountMatches($payment, $input)) {
            Log::warning('Tour VNPay IPN: amount mismatch', ['payment_id' => $payment->id]);

            return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
        }

        $eventId = 'tour:vnpay:'.($input['vnp_TxnRef'] ?? '').':'.($input['vnp_TransactionNo'] ?? '');
        try {
            WebhookEvent::create([
                'provider' => 'vnpay',
                'event_id' => $eventId,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
        }

        ProcessTourIpn::dispatch($eventId, $payment->id, $input);

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
    }
}
