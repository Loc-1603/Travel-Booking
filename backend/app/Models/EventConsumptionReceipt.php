<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventConsumptionReceipt extends Model
{
    protected $fillable = [
        'consumer',
        'event_id',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
