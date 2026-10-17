<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment;

    public function findByTxnRef(string $txnRef, string $provider = 'vnpay'): ?Payment;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Payment;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Payment $payment, array $attributes): Payment;
}
