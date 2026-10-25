<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'event_version',
        'payload',
        'available_at',
        'published_at',
        'attempts',
        'locked_at',
        'lease_expires_at',
        'last_error',
    ];

    protected $casts = [
        'event_version' => 'integer',
        'payload' => 'array',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
        'attempts' => 'integer',
        'locked_at' => 'datetime',
        'lease_expires_at' => 'datetime',
    ];
}
