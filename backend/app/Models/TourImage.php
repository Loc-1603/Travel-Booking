<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'url',
        'alt_text',
        'sort_order',
        'is_banner',
    ];

    protected function casts(): array
    {
        return [
            'is_banner' => 'boolean',
        ];
    }

    public function getUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        return asset('storage/' . ltrim($value, '/'));
    }

    public function tour()
    {
        return $this->belongsTo(TourProduct::class, 'tour_id');
    }
}
