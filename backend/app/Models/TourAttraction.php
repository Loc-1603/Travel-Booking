<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourAttraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'name',
        'description',
        'image',
        'latitude',
        'longitude',
        'is_famous',
    ];

    protected function casts(): array
    {
        return [
            'is_famous' => 'boolean',
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

    public function province()
    {
        return $this->belongsTo(TourProvince::class, 'province_id');
    }
}
