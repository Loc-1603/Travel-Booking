<?php

namespace App\Console\Commands;

use App\Actions\PublishOutboxEvents;
use Illuminate\Console\Command;

class ReplayOutboxEventCommand extends Command
{
    protected $signature = 'outbox:replay
        {event_id? : Outbox event_id to replay}
        {--all-failed : Replay all failed outbox events}';

    protected $description = 'Replay a failed outbox event after operator review';

    public function handle(PublishOutboxEvents $publisher): int
    {
        if ($this->option('all-failed')) {
            $events = \App\Models\OutboxEvent::query()
                ->whereNull('published_at')
                ->where('attempts', '>=', (int) config('outbox.max_attempts', 3))
                ->pluck('event_id');

            $replayed = 0;
            foreach ($events as $eventId) {
                if ($publisher->replay($eventId)) {
                    $replayed++;
                }
            }

            $this->info("Replayed {$replayed} failed outbox event(s).");

            return self::SUCCESS;
        }

        $eventId = (string) $this->argument('event_id');

        if ($eventId === '') {
            $this->error('An event_id is required unless --all-failed is used.');

            return self::FAILURE;
        }

        if (! $publisher->replay($eventId)) {
            $this->error("Outbox event not found: {$eventId}");

            return self::FAILURE;
        }

        $this->info("Outbox event queued for replay: {$eventId}");

        return self::SUCCESS;
    }
}
