<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TourProvider extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'vendor_id',
        'business_name',
        'bio',
        'bio_json',
        'bio_html',
        'avatar',
        'languages',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'bio_json' => 'array',
        ];
    }

    /**
     * Public avatar URL. External (http) URLs are kept as-is (e.g. seed
     * data), local paths are resolved via the public disk — same pattern
     * as TourAttraction/TourProvince/TourImage accessors.
     */
    public function getAvatarAttribute($value)
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, 'http')) {
            return $value;
        }

        return asset('storage/'.ltrim($value, '/'));
    }

    /**
     * Effective avatar: own upload first, then the vendor account avatar.
     */
    public function resolvedAvatarUrl(): ?string
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        return $this->vendor?->avatarUrl();
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function tours()
    {
        return $this->hasMany(TourProduct::class, 'provider_id');
    }

    public function bookings()
    {
        return $this->hasMany(TourBooking::class, 'provider_id');
    }
}
