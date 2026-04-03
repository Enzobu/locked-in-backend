<?php

declare(strict_types=1);

namespace App\Service\Locker;

final class LockerCommandResult
{
    public function __construct(
        public readonly int $statusCode,
        public readonly array $data,
    ) {
    }
}
