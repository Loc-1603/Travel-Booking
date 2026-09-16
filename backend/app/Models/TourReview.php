<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_booking_id',
        'rating',
        'comment',
        'approved',
        'hidden',
        'moderated_at',
        'moderated_by',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'hidden' => 'boolean',
            'moderated_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id');
    }

    public function images()
    {
        return $this->hasMany(TourReviewImage::class, 'tour_review_id')->orderBy('sort_order');
    }

    public function moderatedBy()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
