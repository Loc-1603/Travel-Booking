<?php

namespace App\Repositories\Eloquent;

use App\Models\TourPayment;
use App\Repositories\Contracts\TourPaymentRepositoryInterface;

class EloquentTourPaymentRepository implements TourPaymentRepositoryInterface
{
    public function findById(int $id): ?TourPayment
    {
        return TourPayment::find($id);
    }

    public function findByIdForUpdate(int $id): ?TourPayment
    {
        return TourPayment::whereKey($id)->lockForUpdate()->first();
    }

    public function findByTxnRef(string $txnRef, string $provider = 'vnpay'): ?TourPayment
    {
        return TourPayment::where('provider', $provider)
            ->where('external_id', $txnRef)
            ->first();
    }

    public function create(array $attributes): TourPayment
    {
        return TourPayment::create($attributes);
    }

    public function update(TourPayment $payment, array $attributes): TourPayment
    {
        $payment->update($attributes);

        return $payment->fresh() ?? $payment;
    }
}
