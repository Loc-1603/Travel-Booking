<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;
use Illuminate\Support\Str;

/**
 * VNPay URL builder for tour_bookings.
 */
class TourVnpayAdapter
{
    public function __construct(
        protected VnpayService $vnpay,
        protected TourPaymentRepositoryInterface $payments,
    ) {}

    public function isConfigured(): bool
    {
        return $this->vnpay->isConfigured();
    }

    /**
     * @return array{payment_url: string, payment_id: int, txn_ref: string}
     */
    public function createPaymentUrl(\App\Models\TourBooking $booking, ?string $ipAddr = null, ?string $bankCode = null): array
    {
        if (! $this->vnpay->isConfigured()) {
            throw new \RuntimeException('VNPay is not configured. Set VNPAY_TMN_CODE and VNPAY_HASH_SECRET in .env.');
        }

        $amountVnd = (int) round((float) $booking->total_price);
        if ($amountVnd <= 0) {
            throw new \RuntimeException('Invalid tour booking amount for VNPay payment.');
        }

        $txnRef = 'tour_'.$booking->id.'_'.now()->format('YmdHis').'_'.Str::upper(Str::random(4));

        $now = now('Asia/Ho_Chi_Minh');

        $payment = $this->payments->create([
            'tour_booking_id' => $booking->id,
            'amount' => $amountVnd,
            'currency' => 'VND',
            'provider' => 'vnpay',
            'external_id' => $txnRef,
            'status' => PaymentStatus::PENDING->value,
            'payload' => [
                'txn_ref' => $txnRef,
                'vnp_create_date' => $now->format('YmdHis'),
            ],
        ]);

        $expire = $now->copy()->addMinutes((int) config('services.vnpay.expire_minutes', 30));

        $params = [
            'vnp_Version' => config('services.vnpay.version', '2.1.0'),
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->vnpay->tmnCode(),
            'vnp_Amount' => $amountVnd * 100,
            'vnp_CurrCode' => config('services.vnpay.currency', 'VND'),
            'vnp_TxnRef' => $txnRef,
            'vnp_OrderInfo' => "Thanh toan tour {$booking->uuid}",
            'vnp_OrderType' => 'other',
            'vnp_Locale' => config('services.vnpay.locale', 'vn'),
            'vnp_ReturnUrl' => $this->returnUrl($booking),
            'vnp_IpAddr' => $ipAddr ?? request()->ip() ?? '127.0.0.1',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_ExpireDate' => $expire->format('YmdHis'),
        ];
        if ($bankCode) {
            $params['vnp_BankCode'] = $bankCode;
        }

        ksort($params);
        $query = '';
        foreach ($params as $key => $value) {
            $query .= urlencode((string) $key).'='.urlencode((string) $value).'&';
        }
        $paymentUrl = rtrim((string) config('services.vnpay.url'), '?').'?'.$query.'vnp_SecureHash='.$this->vnpay->signature($params);

        return [
            'payment_url' => $paymentUrl,
            'payment_id' => $payment->id,
            'txn_ref' => $txnRef,
        ];
    }

    public function verifySignature(array $input): bool
    {
        return $this->vnpay->verifySignature($input);
    }

    public function findPaymentForIpn(array $input): ?\App\Models\TourPayment
    {
        $txnRef = (string) ($input['vnp_TxnRef'] ?? '');
        if ($txnRef === '') {
            return null;
        }

        return $this->payments->findByTxnRef($txnRef, 'vnpay');
    }

    public function amountMatches(\App\Models\TourPayment $payment, array $input): bool
    {
        $vnpAmount = (int) ($input['vnp_Amount'] ?? 0);

        return $vnpAmount === (int) round((float) $payment->amount) * 100;
    }

    public function recordTransactionNo(\App\Models\TourPayment $payment, array $input): void
    {
        $transactionNo = (string) ($input['vnp_TransactionNo'] ?? '');
        if ($transactionNo === '') {
            return;
        }

        $payload = $payment->payload ?? [];
        $payload['vnp_transaction_no'] = $transactionNo;

        $payDate = (string) ($input['vnp_PayDate'] ?? '');
        if ($payDate !== '') {
            $payload['vnp_pay_date'] = $payDate;
        }

        $this->payments->update($payment, ['payload' => $payload]);
    }

    protected function returnUrl(\App\Models\TourBooking $booking): string
    {
        $base = rtrim((string) config('services.vnpay.return_url'), '/');

        return $base.'?uuid='.$booking->uuid.'&type=tour';
    }
}
