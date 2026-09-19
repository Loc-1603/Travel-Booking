<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_booking_id',
        'sender_id',
        'body',
        'attachments',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Count unread messages across all of a vendor's tour providers.
     */
    public static function unreadForVendor(int $vendorId): int
    {
        $providerIds = TourProvider::where('vendor_id', $vendorId)->pluck('id');

        if ($providerIds->isEmpty()) {
            return 0;
        }

        return static::whereHas('booking', function ($query) use ($providerIds) {
            $query->whereIn('provider_id', $providerIds);
        })
            ->where('sender_id', '!=', $vendorId)
            ->whereNull('read_at')
            ->count();
    }
}
