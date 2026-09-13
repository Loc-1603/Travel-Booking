<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_booking_id',
        'amount',
        'currency',
        'provider',
        'external_id',
        'status',
        'payload',
        'refunded_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'payload' => 'array',
    ];

    public function booking()
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id');
    }

    /**
     * For policy vendor isolation: payment is accessed via booking -> provider -> vendor_id.
     */
    public function getVendorIdAttribute(): ?int
    {
        return $this->booking?->provider?->vendor_id;
    }
}
