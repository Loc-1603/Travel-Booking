<?php

namespace App\Console\Commands;

use App\Enums\RefundStatus;
use App\Repositories\Contracts\PaymentRefundRepositoryInterface;
use App\Services\RefundService;
use App\Services\VnpayRefundClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessRefundsCommand extends Command
{
    protected $signature = 'refunds:process
        {--limit=100 : Maximum refunds claimed per run}
        {--lease=90 : Lease length in seconds}';

    protected $description = 'Claim due VNPay refund obligations and send them (safety net for queued jobs)';

    public function handle(
        PaymentRefundRepositoryInterface $refunds,
        RefundService $service,
        VnpayRefundClient $client,
    ): int {
        if (! $client->enabled()) {
            $this->warn('VNPay refunds are disabled; set VNPAY_REFUND_ENABLED=true to process obligations.');

            return self::SUCCESS;
        }

        $refunds->releaseStaleLeases();

        $limit = max(1, (int) $this->option('limit'));
        $lease = max(1, (int) $this->option('lease'));
        $claimed = $refunds->claimBatch($limit, $lease);

        $processed = 0;
        foreach ($claimed as $refund) {
            try {
                $service->process((int) $refund->id, $refund->lease_expires_at);
                $processed++;
            } catch (Throwable $e) {
                Log::error('refunds:process failed for refund', [
                    'refund_id' => $refund->id,
                    'request_id' => $refund->request_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Refund #{$refund->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$processed} of {$claimed->count()} claimed refund(s).");

        $counts = $refunds->countByStatuses([
            RefundStatus::PENDING->value,
            RefundStatus::PROCESSING->value,
            RefundStatus::FAILED->value,
            RefundStatus::REJECTED->value,
            RefundStatus::NEEDS_REVIEW->value,
        ]);

        Log::info('refund.batch_summary', [
            'claimed' => $claimed->count(),
            'processed' => $processed,
            'pending_count' => $counts[RefundStatus::PENDING->value] ?? 0,
            'processing_count' => $counts[RefundStatus::PROCESSING->value] ?? 0,
            'failed_count' => ($counts[RefundStatus::FAILED->value] ?? 0)
                + ($counts[RefundStatus::REJECTED->value] ?? 0),
            'needs_review_count' => $counts[RefundStatus::NEEDS_REVIEW->value] ?? 0,
        ]);

        return self::SUCCESS;
    }
}
