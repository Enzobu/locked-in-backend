<?php

declare(strict_types=1);

namespace App\Service\Locker;

use App\Entity\Locker;

interface LockerGatewayInterface
{
    public function open(Locker $locker): LockerCommandResult;
}
