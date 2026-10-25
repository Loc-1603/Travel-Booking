<?php

namespace App\Console\Commands;

use App\Actions\PublishOutboxEvents;
use Illuminate\Console\Command;

class PublishOutboxEventsCommand extends Command
{
    protected $signature = 'outbox:publish
        {--limit= : Maximum number of events to claim}
        {--lease= : Lease duration in seconds}';

    protected $aliases = ['outbox:publish-events', 'outbox:dispatch'];

    protected $description = 'Claim and publish pending outbox events';

    public function handle(PublishOutboxEvents $publisher): int
    {
        $limit = (int) ($this->option('limit') ?: config('outbox.batch_size', 100));
        $lease = (int) ($this->option('lease') ?: config('outbox.lease_seconds', 90));

        $result = $publisher->handle($limit, $lease);

        $this->info(sprintf(
            'Outbox publish complete: claimed=%d, published=%d, retried=%d, failed=%d.',
            $result['claimed'],
            $result['published'],
            $result['retried'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
