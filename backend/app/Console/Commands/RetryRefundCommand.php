<?php

namespace App\Console\Commands;

use App\Enums\RefundStatus;
use App\Jobs\ProcessVnpayRefund;
use App\Repositories\Contracts\PaymentRefundRepositoryInterface;
use App\Services\VnpayRefundClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryRefundCommand extends Command
{
    protected $signature = 'refunds:retry
        {refund : Refund id or uuid}
        {--force : Replay even when VNPay already accepted the original request}';

    protected $description = 'Replay one refund obligation with the same VNPay request id after operator review';

    public function handle(
        PaymentRefundRepositoryInterface $refunds,
        VnpayRefundClient $client,
    ): int {
        if (! $client->enabled()) {
            $this->error('VNPay refunds are disabled; set VNPAY_REFUND_ENABLED=true before replaying.');

            return self::FAILURE;
        }

        $identifier = (string) $this->argument('refund');
        $refund = ctype_digit($identifier)
            ? $refunds->findById((int) $identifier)
            : $refunds->findByUuid($identifier);

        if ($refund === null) {
            $this->error("Refund not found: {$identifier}");

            return self::FAILURE;
        }

        $accepted = $refund->status === RefundStatus::SUCCEEDED->value
            || ($refund->status === RefundStatus::PROCESSING->value
                && $refund->last_error === null
                && in_array((string) $refund->vnp_response_code, ['00', '94'], true));

        if ($accepted && ! $this->option('force')) {
            $this->error('Refund was already accepted by VNPay; use --force only after reconciling the original request.');

            return self::FAILURE;
        }

        $previousStatus = $refund->status;
        $refund = $refunds->markPendingForReplay($refund);

        Log::info('refund.replay', [
            'refund_id' => $refund->id,
            'uuid' => $refund->uuid,
            'request_id' => $refund->request_id,
            'payment_type' => $refund->payment_type,
            'payment_id' => $refund->payment_id,
            'previous_status' => $previousStatus,
            'force' => (bool) $this->option('force'),
            'operator' => get_current_user() ?: 'cli',
        ]);

        ProcessVnpayRefund::dispatch((int) $refund->id);

        $this->info("Refund #{$refund->id} queued for replay with request id {$refund->request_id}.");

        return self::SUCCESS;
    }
}
