<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A registration that has not been verified yet. No users row exists until
 * the emailed verification link is confirmed.
 */
class PendingRegistration extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'business_name',
        'business_details',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}