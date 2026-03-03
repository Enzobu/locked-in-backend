<?php

namespace App\Enum;

enum LockerActionStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
