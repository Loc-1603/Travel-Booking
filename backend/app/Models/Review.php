<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Đánh giá dùng chung cho hotel (booking_id) và tour/guide (tour_booking_id).
 *
 * Review hiển thị ngay khi khách gửi; admin chỉ hậu kiểm bằng hidden.
 */
class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'tour_booking_id',
        'rating',
        'comment',
        'hidden',
        'moderated_at',
        'moderated_by',
    ];

    protected function casts(): array
    {
        return [
            'hidden' => 'boolean',
            'moderated_at' => 'datetime',
        ];
    }

    public function moderatedBy()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function tourBooking()
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id');
    }

    public function images()
    {
        return $this->hasMany(ReviewImage::class, 'review_id')->orderBy('sort_order');
    }

    /**
     * Chỉ review đang hiển thị (admin chưa ẩn).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('hidden', false);
    }

    /**
     * Review của một hotel (qua hotel booking).
     */
    public function scopeForHotel(Builder $query, int $hotelId): Builder
    {
        return $query->whereNotNull('booking_id')
            ->whereHas('booking', fn (Builder $q) => $q->where('hotel_id', $hotelId));
    }

    /**
     * Review của một tour (qua tour booking).
     */
    public function scopeForTour(Builder $query, string $tourUuid): Builder
    {
        return $query->whereNotNull('tour_booking_id')
            ->whereHas('tourBooking.tour', fn (Builder $q) => $q->where('uuid', $tourUuid));
    }

    /**
     * Review của một guide/provider (mọi tour booking của guide đó).
     */
    public function scopeForProvider(Builder $query, string $providerUuid): Builder
    {
        return $query->whereNotNull('tour_booking_id')
            ->whereHas('tourBooking.provider', fn (Builder $q) => $q->where('uuid', $providerUuid));
    }
}
