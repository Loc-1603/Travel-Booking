<?php

namespace App\Repositories\Contracts;

use App\Models\TourPayment;

interface TourPaymentRepositoryInterface
{
    public function findById(int $id): ?TourPayment;

    public function findByIdForUpdate(int $id): ?TourPayment;

    public function findByTxnRef(string $txnRef, string $provider = 'vnpay'): ?TourPayment;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TourPayment;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TourPayment $payment, array $attributes): TourPayment;
}
