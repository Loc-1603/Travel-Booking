<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\PaymentRefundRepositoryInterface;
use App\Services\RefundService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SettleRefundCommand extends Command
{
    protected $signature = 'refunds:settle
        {refund : Refund id or uuid}
        {--note= : Reference/reason for the off-platform refund (required)}
        {--operator= : Operator identifier recorded in the audit trail}';

    protected $description = 'Record an off-platform refund and close its failed/needs_review obligation';

    public function handle(
        PaymentRefundRepositoryInterface $refunds,
        RefundService $service,
    ): int {
        $identifier = (string) $this->argument('refund');
        $refund = ctype_digit($identifier)
            ? $refunds->findById((int) $identifier)
            : $refunds->findByUuid($identifier);

        if ($refund === null) {
            $this->error("Refund not found: {$identifier}");

            return self::FAILURE;
        }

        $note = trim((string) ($this->option('note') ?? ''));
        if ($note === '') {
            $this->error('Use --note to record the manual refund reference/reason.');

            return self::FAILURE;
        }

        $operator = trim((string) ($this->option('operator') ?? '')) ?: (get_current_user() ?: 'cli');

        try {
            $settled = $service->settleManually($refund, $note, $operator);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Refund #{$settled->id} marked as manually settled.");

        return self::SUCCESS;
    }
}
