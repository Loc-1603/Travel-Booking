<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment
    {
        return Payment::find($id);
    }

    public function findByIdForUpdate(int $id): ?Payment
    {
        return Payment::whereKey($id)->lockForUpdate()->first();
    }

    public function findByTxnRef(string $txnRef, string $provider = 'vnpay'): ?Payment
    {
        return Payment::where('provider', $provider)
            ->where('external_id', $txnRef)
            ->first();
    }

    public function create(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function update(Payment $payment, array $attributes): Payment
    {
        $payment->update($attributes);

        return $payment->fresh() ?? $payment;
    }
}
