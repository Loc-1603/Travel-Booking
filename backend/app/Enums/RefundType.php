<?php

namespace App\Enums;

enum RefundType: string
{
    case FULL = '02';
    case PARTIAL = '03';
}
