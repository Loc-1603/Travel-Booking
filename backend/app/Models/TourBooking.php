<?php

namespace App\Models;

use App\Enums\TourBookingStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TourBooking extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'customer_id',
        'tour_id',
        'slot_id',
        'provider_id',
        'province_id',
        'start_at',
        'end_at',
        'pricing_mode',
        'duration_value',
        'base_fixed',
        'unit_price',
        'subtotal',
        'transport_fee',
        'discount_amount',
        'tax_amount',
        'total_price',
        'currency',
        'status',
        'coupon_id',
        'meeting_point',
        'customer_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'base_fixed' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'transport_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function tour()
    {
        return $this->belongsTo(TourProduct::class, 'tour_id');
    }

    public function slot()
    {
        return $this->belongsTo(TourAvailabilitySlot::class, 'slot_id');
    }

    public function provider()
    {
        return $this->belongsTo(TourProvider::class, 'provider_id');
    }

    public function payments()
    {
        return $this->hasMany(TourPayment::class, 'tour_booking_id');
    }

    public function review()
    {
        return $this->hasOne(TourReview::class, 'tour_booking_id');
    }

    public function dispute()
    {
        return $this->hasOne(TourDispute::class, 'tour_booking_id');
    }

    public function messages()
    {
        return $this->hasMany(TourMessage::class, 'tour_booking_id')->orderBy('created_at');
    }

    public function canOpenDispute(): bool
    {
        if ($this->status === TourBookingStatus::PENDING_PAYMENT->value) {
            return false;
        }

        return ! $this->dispute()->exists();
    }

    public function isPaid(): bool
    {
        return $this->status === TourBookingStatus::CONFIRMED->value;
    }
}
