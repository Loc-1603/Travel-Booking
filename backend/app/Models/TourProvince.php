<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourProvince extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'city_id',
        'name',
        'slug',
        'description',
        'image',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    public function getImageAttribute($value)
    {
        if (!$value) {
            return null;
        }
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        return asset('storage/' . ltrim($value, '/'));
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function attractions()
    {
        return $this->hasMany(TourAttraction::class, 'province_id');
    }

    public function tours()
    {
        return $this->hasMany(TourProduct::class, 'province_id');
    }
}
