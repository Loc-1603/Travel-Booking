<?php

namespace App\Repositories\Eloquent;

use App\Models\WebhookEvent;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;

class EloquentWebhookEventRepository implements WebhookEventRepositoryInterface
{
    public function find(string $provider, string $eventId): ?WebhookEvent
    {
        return WebhookEvent::where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();
    }

    public function create(array $attributes): WebhookEvent
    {
        return WebhookEvent::create($attributes);
    }

    public function update(WebhookEvent $event, array $attributes): WebhookEvent
    {
        $event->update($attributes);

        return $event->fresh() ?? $event;
    }
}
