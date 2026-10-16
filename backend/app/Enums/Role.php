<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case VENDOR = 'vendor';
    case GUIDE = 'guide';
    case CUSTOMER = 'customer';
}

