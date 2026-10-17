<?php

namespace App\Repositories\Contracts;

use App\Models\WebhookEvent;

interface WebhookEventRepositoryInterface
{
    public function find(string $provider, string $eventId): ?WebhookEvent;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): WebhookEvent;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WebhookEvent $event, array $attributes): WebhookEvent;
}
