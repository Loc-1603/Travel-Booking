<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourAvailabilitySlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'held_until',
        'tour_booking_id',
        'price_override',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'held_until' => 'datetime',
            'price_override' => 'decimal:2',
        ];
    }

    public function tour()
    {
        return $this->belongsTo(TourProduct::class, 'tour_id');
    }

    public function booking()
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id');
    }
}
