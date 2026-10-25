<?php

namespace App\Jobs;

use App\Services\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Attempt one VNPay refund call for a persisted payment_refunds row.
 *
 * The row (not the job) is the source of truth: if the worker dies, the
 * refunds:process command re-claims it after the lease expires.
 */
class ProcessVnpayRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retries are owned by the refund row (next_retry_at), not the queue.
     */
    public int $tries = 1;

    public function __construct(public int $refundId) {}

    public function handle(RefundService $refunds): void
    {
        $refunds->process($this->refundId);
    }
}
