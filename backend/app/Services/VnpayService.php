<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * VNPay integration (payment URL + IPN verification).
 *
 * All credentials come from config/services.php (env only).
 * Amounts are in VND. VNPay requires vnp_Amount = amount x 100.
 *
 * @see https://sandbox.vnpayment.vn/apis/docs/huong-dan-tich-hop/
 */
class VnpayService
{
    public function isConfigured(): bool
    {
        return $this->tmnCode() !== '' && $this->hashSecret() !== '';
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('VNPay is not configured. Set VNPAY_TMN_CODE and VNPAY_HASH_SECRET in .env.');
        }
    }

    public function tmnCode(): string
    {
        return (string) config('services.vnpay.tmn_code', '');
    }

    protected function hashSecret(): string
    {
        return (string) config('services.vnpay.hash_secret', '');
    }

    /**
     * Build a VNPay payment URL for a booking and persist a pending Payment row.
     *
     * @return array{payment_url: string, payment_id: int, txn_ref: string}
     */
    public function createPaymentUrl(Booking $booking, ?string $ipAddr = null, ?string $bankCode = null): array
    {
        $this->assertConfigured();

        $amountVnd = (int) round((float) $booking->total_price);
        if ($amountVnd <= 0) {
            throw new \RuntimeException('Invalid booking amount for VNPay payment.');
        }

        // Unique per payment attempt: booking + second-precision timestamp + random
        // suffix, so two attempts within the same second never share a TxnRef.
        $txnRef = $booking->id.'_'.now()->format('YmdHis').'_'.Str::upper(Str::random(4));

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amountVnd,
            'currency' => 'VND',
            'provider' => 'vnpay',
            'external_id' => $txnRef,
            'status' => \App\Enums\PaymentStatus::PENDING->value,
            'payload' => ['txn_ref' => $txnRef],
        ]);

        $now = now();
        $expire = $now->copy()->addMinutes((int) config('services.vnpay.expire_minutes', 30));

        $params = [
            'vnp_Version' => config('services.vnpay.version', '2.1.0'),
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->tmnCode(),
            'vnp_Amount' => $amountVnd * 100,
            'vnp_CurrCode' => config('services.vnpay.currency', 'VND'),
            'vnp_TxnRef' => $txnRef,
            'vnp_OrderInfo' => "Thanh toan dat phong {$booking->uuid}",
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

        $paymentUrl = rtrim((string) config('services.vnpay.url'), '?').'?'.$this->buildQuery($params);

        return [
            'payment_url' => $paymentUrl,
            'payment_id' => $payment->id,
            'txn_ref' => $txnRef,
        ];
    }

    /**
     * Verify VNPay return/IPN params signature.
     */
    public function verifySignature(array $input): bool
    {
        $this->assertConfigured();

        $received = (string) ($input['vnp_SecureHash'] ?? '');
        if ($received === '') {
            return false;
        }

        return hash_equals(strtolower($received), strtolower($this->signature($input)));
    }

    /**
     * Find the pending Payment row matching VNPay IPN params (after signature check).
     */
    public function findPaymentForIpn(array $input): ?Payment
    {
        $txnRef = (string) ($input['vnp_TxnRef'] ?? '');
        if ($txnRef === '') {
            return null;
        }

        return Payment::where('provider', 'vnpay')
            ->where('external_id', $txnRef)
            ->first();
    }

    /**
     * Check IPN amount matches the payment amount.
     */
    public function amountMatches(Payment $payment, array $input): bool
    {
        $vnpAmount = (int) ($input['vnp_Amount'] ?? 0);

        return $vnpAmount === (int) round((float) $payment->amount) * 100;
    }

    /**
     * Record the VNPay transaction number on the payment (for reconciliation).
     */
    public function recordTransactionNo(Payment $payment, array $input): void
    {
        $transactionNo = (string) ($input['vnp_TransactionNo'] ?? '');
        if ($transactionNo === '') {
            return;
        }

        $payload = $payment->payload ?? [];
        $payload['vnp_transaction_no'] = $transactionNo;
        $payment->update(['payload' => $payload]);
    }

    protected function returnUrl(Booking $booking): string
    {
        $base = rtrim((string) config('services.vnpay.return_url'), '/');

        return $base.'?uuid='.$booking->uuid;
    }

    /**
     * Build sorted, url-encoded query string with trailing HMAC-SHA512 signature.
     */
    protected function buildQuery(array $params): string
    {
        ksort($params);
        $query = '';
        foreach ($params as $key => $value) {
            $query .= urlencode((string) $key).'='.urlencode((string) $value).'&';
        }

        return $query.'vnp_SecureHash='.$this->signature($params);
    }

    /**
     * Compute HMAC-SHA512 signature for the given params.
     * Only vnp_* keys are signed; vnp_SecureHash / vnp_SecureHashType excluded.
     * Public so tests and tooling can sign simulated IPN payloads.
     */
    public function signature(array $input): string
    {
        $data = [];
        foreach ($input as $key => $value) {
            if (str_starts_with((string) $key, 'vnp_')
                && $key !== 'vnp_SecureHash'
                && $key !== 'vnp_SecureHashType') {
                $data[$key] = $value;
            }
        }
        ksort($data);

        $hashData = '';
        $i = 0;
        foreach ($data as $key => $value) {
            $hashData .= ($i++ === 0 ? '' : '&').urlencode((string) $key).'='.urlencode((string) $value);
        }

        return hash_hmac('sha512', $hashData, $this->hashSecret());
    }
}
