<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'name',
        'capacity',
        'base_price',
        'total_rooms',
        'cancellation_policy',
        'description',
        'size',
        'bed_type',
        'view_type',
    ];

    protected function casts(): array
    {
        return [
            'cancellation_policy' => 'array',
            'size' => 'decimal:2',
        ];
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function availability()
    {
        return $this->hasMany(RoomAvailability::class);
    }

    public function images()
    {
        return $this->hasMany(\App\Models\RoomImage::class)->ordered();
    }

    public function bannerImage()
    {
        return $this->hasOne(\App\Models\RoomImage::class)->banner();
    }

    public function amenities()
    {
        return $this->belongsToMany(\App\Models\Amenity::class, 'room_amenity')->orderBy('amenities.sort_order');
    }
}

