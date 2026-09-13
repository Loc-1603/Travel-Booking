<?php

namespace App\Enums;

enum TourSlotStatus: string
{
    case AVAILABLE = 'available';
    case HELD = 'held';
    case BOOKED = 'booked';
    case BLOCKED = 'blocked';
}
