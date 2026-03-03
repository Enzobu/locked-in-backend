<?php

namespace App\Enum;

enum LockerStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case OCCUPIED = 'occupied';
    case OUT_OF_ORDER = 'out_of_order';
    case OFFLINE = 'offline';
}
