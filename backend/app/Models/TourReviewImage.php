<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourReviewImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_review_id',
        'path',
        'sort_order',
    ];

    /**
     * Public URL: external giữ nguyên, local qua public disk.
     */
    public function getUrlAttribute(): ?string
    {
        $path = $this->attributes['path'] ?? null;
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    public function review()
    {
        return $this->belongsTo(TourReview::class, 'tour_review_id');
    }
}
