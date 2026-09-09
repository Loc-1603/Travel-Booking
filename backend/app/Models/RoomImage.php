<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomImage extends Model
{
    protected $fillable = [
        'room_id',
        'image_path',
        'alt_text',
        'is_banner',
        'sort_order',
    ];

    protected $casts = [
        'is_banner' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function room()
    {
        return $this->belongsTo(\App\Models\Room::class);
    }

    public function getImageUrlAttribute()
    {
        if (str_starts_with((string) $this->image_path, 'http')) {
            return $this->image_path;
        }

        return asset('storage/' . $this->image_path);
    }

    public function scopeBanner($query)
    {
        return $query->where('is_banner', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
