<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_booking_id',
        'status',
        'contact_name',
        'contact_email',
        'contact_phone',
        'customer_notes',
        'internal_notes',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
