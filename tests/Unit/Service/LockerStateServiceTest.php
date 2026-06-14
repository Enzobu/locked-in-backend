<?php

namespace App\Tests\Unit\Service;

use App\Entity\Locker;
use App\Enum\LockerStatus;
use App\Repository\ReservationRepository;
use App\Service\Locker\LockerStateService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockerStateService::class)]
final class LockerStateServiceTest extends TestCase
{
    private function service(bool $occupying, bool $active): LockerStateService
    {
        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('hasOccupyingReservation')->willReturn($occupying);
        $repository->method('hasActiveReservation')->willReturn($active);

        return new LockerStateService($repository);
    }

    public function testBecomesOccupiedWhenAReservationCoversNow(): void
    {
        $locker = (new Locker())->setStatus(LockerStatus::AVAILABLE);
        $this->service(occupying: true, active: true)->recompute($locker);

        self::assertSame(LockerStatus::OCCUPIED, $locker->getStatus());
    }

    public function testBecomesReservedWithAnUpcomingConfirmedReservation(): void
    {
        $locker = (new Locker())->setStatus(LockerStatus::AVAILABLE);
        $this->service(occupying: false, active: true)->recompute($locker);

        self::assertSame(LockerStatus::RESERVED, $locker->getStatus());
    }

    public function testBecomesAvailableWithNoActiveReservation(): void
    {
        $locker = (new Locker())->setStatus(LockerStatus::RESERVED);
        $this->service(occupying: false, active: false)->recompute($locker);

        self::assertSame(LockerStatus::AVAILABLE, $locker->getStatus());
    }

    public function testOutOfOrderIsSticky(): void
    {
        $locker = (new Locker())->setStatus(LockerStatus::OUT_OF_ORDER);
        $this->service(occupying: true, active: true)->recompute($locker);

        self::assertSame(LockerStatus::OUT_OF_ORDER, $locker->getStatus());
    }
}
