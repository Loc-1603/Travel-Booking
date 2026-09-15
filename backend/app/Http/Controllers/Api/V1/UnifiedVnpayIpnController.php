<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Jobs\ProcessTourIpn;
use App\Jobs\ProcessVnpayIpn;
use App\Models\Payment;
use App\Models\TourPayment;
use App\Models\WebhookEvent;
use App\Services\TourVnpayAdapter;
use App\Services\VnpayService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Unified VNPay IPN (server callback) endpoint for hotel + tour payments.
 *
 * VNPay allows a single IPN URL per TmnCode, so both payment types resolve
 * here. Routing is by vnp_TxnRef prefix (tour refs are generated as
 * "tour_..."), with a cross-table fallback lookup for safety.
 *
 * Flow (same contract as the two legacy endpoints): verify checksum,
 * find payment, reject already-confirmed (02) and amount mismatches (04),
 * enforce idempotency via webhook_events, then dispatch async processing.
 * Responds with VNPay result codes: 00 success, 01 order not found,
 * 02 already confirmed, 04 invalid amount, 97 invalid signature.
 */
class UnifiedVnpayIpnController extends BaseApiController
{
    public function __construct(
        protected VnpayService $vnpay,
        protected TourVnpayAdapter $tours,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->query();

        if (! $this->vnpay->verifySignature($input)) {
            Log::warning('VNPay IPN: invalid signature', ['txn_ref' => $input['vnp_TxnRef'] ?? null]);

            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }

        $resolved = $this->resolvePayment($input);
        if ($resolved === null) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }
        [$isTour, $payment] = $resolved;

        if ($payment->status === PaymentStatus::COMPLETED->value) {
            return response()->json(['RspCode' => '02', 'Message' => 'Order already confirmed']);
        }

        $amountOk = $isTour
            ? $this->tours->amountMatches($payment, $input)
            : $this->vnpay->amountMatches($payment, $input);
        if (! $amountOk) {
            Log::warning('VNPay IPN: amount mismatch', [
                'payment_id' => $payment->id,
                'type' => $isTour ? 'tour' : 'hotel',
            ]);

            return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
        }

        $prefix = $isTour ? 'tour:vnpay:' : 'vnpay:';
        $eventId = $prefix.($input['vnp_TxnRef'] ?? '').':'.($input['vnp_TransactionNo'] ?? '');
        try {
            WebhookEvent::create([
                'provider' => 'vnpay',
                'event_id' => $eventId,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
        }

        if ($isTour) {
            ProcessTourIpn::dispatch($eventId, $payment->id, $input);
        } else {
            ProcessVnpayIpn::dispatch($eventId, $payment->id, $input);
        }

        return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
    }

    /**
     * Find the payment row for an IPN, honouring the txn_ref namespace first
     * and falling back to the other table so either ref format resolves.
     *
     * @return array{0: bool, 1: Payment|TourPayment}|null bool = is tour payment
     */
    protected function resolvePayment(array $input): ?array
    {
        $tourFirst = str_starts_with((string) ($input['vnp_TxnRef'] ?? ''), 'tour_');

        $findHotel = fn (): ?Payment => $this->vnpay->findPaymentForIpn($input);
        $findTour = fn (): ?TourPayment => $this->tours->findPaymentForIpn($input);

        foreach ($tourFirst ? ['tour', 'hotel'] : ['hotel', 'tour'] as $type) {
            $payment = $type === 'tour' ? $findTour() : $findHotel();
            if ($payment !== null) {
                return [$type === 'tour', $payment];
            }
        }

        return null;
    }
}
