<?php

namespace App\Tests\Unit\Service\Locker;

use App\Entity\Locker;
use App\Service\Locker\LockerCommandResult;
use App\Service\Locker\LockerCommandService;
use App\Service\Locker\LockerGatewayInterface;
use PHPUnit\Framework\TestCase;

final class LockerCommandServiceTest extends TestCase
{
    public function testOpenDelegatesToGateway(): void
    {
        $locker = new Locker();
        $result = new LockerCommandResult(200, ['status' => 'opened']);
        $gateway = $this->createMock(LockerGatewayInterface::class);
        $gateway->expects(self::once())->method('open')->with($locker)->willReturn($result);

        self::assertSame($result, (new LockerCommandService($gateway))->open($locker));
    }
}
