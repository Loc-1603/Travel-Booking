<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    use HasFactory;

    public const TYPE_HOTEL = 'hotel';

    public const TYPE_TOUR = 'tour';

    protected $fillable = [
        'uuid',
        'payment_type',
        'payment_id',
        'provider',
        'request_id',
        'idempotency_key',
        'txn_ref',
        'transaction_no',
        'transaction_date',
        'amount',
        'currency',
        'type',
        'status',
        'attempts',
        'next_retry_at',
        'locked_at',
        'lease_expires_at',
        'last_error',
        'vnp_response_code',
        'vnp_transaction_status',
        'vnp_refund_transaction_no',
        'vnp_pay_date',
        'provider_response',
        'reason',
        'requested_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'locked_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'provider_response' => 'array',
        'requested_by' => 'integer',
    ];

    public function isActive(): bool
    {
        return in_array($this->status, RefundStatus::activeValues(), true);
    }
}
