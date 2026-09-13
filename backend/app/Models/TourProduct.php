<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TourProduct extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'provider_id',
        'province_id',
        'title',
        'description',
        'base_fixed',
        'base_price_hourly',
        'base_price_daily',
        'transport_fee',
        'transport_desc',
        'meeting_point',
        'max_group_size',
        'duration_unit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'base_fixed' => 'decimal:2',
            'base_price_hourly' => 'decimal:2',
            'base_price_daily' => 'decimal:2',
            'transport_fee' => 'decimal:2',
        ];
    }

    public function provider()
    {
        return $this->belongsTo(TourProvider::class, 'provider_id');
    }

    public function province()
    {
        return $this->belongsTo(TourProvince::class, 'province_id');
    }

    public function images()
    {
        return $this->hasMany(TourImage::class, 'tour_id')->orderBy('sort_order');
    }

    public function bannerImage()
    {
        return $this->hasOne(TourImage::class, 'tour_id')->where('is_banner', true);
    }

    public function slots()
    {
        return $this->hasMany(TourAvailabilitySlot::class, 'tour_id');
    }

    public function bookings()
    {
        return $this->hasMany(TourBooking::class, 'tour_id');
    }

    public function savedByUsers()
    {
        return $this->hasMany(SavedTour::class, 'tour_id');
    }
}
