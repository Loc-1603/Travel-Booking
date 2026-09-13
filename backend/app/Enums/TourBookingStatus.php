<?php

namespace App\Enums;

enum TourBookingStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case CONFIRMED = 'confirmed';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case DISPUTED = 'disputed';
    case REFUNDED = 'refunded';
}
