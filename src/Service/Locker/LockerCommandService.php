<?php

declare(strict_types=1);

namespace App\Service\Locker;

use App\Entity\Locker;

final class LockerCommandService
{
    public function __construct(
        private readonly LockerGatewayInterface $lockerGateway,
    ) {
    }

    public function open(Locker $locker): LockerCommandResult
    {
        return $this->lockerGateway->open($locker);
    }
}
