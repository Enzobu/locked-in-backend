<?php

namespace App\Tests\Unit\Service;

use App\Entity\Locker;
use App\Entity\LockerBay;
use App\Service\ReservationPricingService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationPricingService::class)]
final class ReservationPricingServiceTest extends TestCase
{
    private function locker(int $priceCents, int $surchargePercent = 0): Locker
    {
        $bay = (new LockerBay())->setOvertimeSurchargePercent($surchargePercent);

        return (new Locker())->setPriceCents($priceCents)->setLockerBay($bay);
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time);
    }

    public function testPlannedAmountIsProRatedPerHour(): void
    {
        $service = new ReservationPricingService();
        $locker = $this->locker(1200); // 12€/hour

        self::assertSame(2400, $service->computePlannedAmountCents($locker, $this->at('2026-07-01T10:00:00Z'), $this->at('2026-07-01T12:00:00Z')));
        self::assertSame(1800, $service->computePlannedAmountCents($locker, $this->at('2026-07-01T10:00:00Z'), $this->at('2026-07-01T11:30:00Z')));
    }

    public function testOvertimeWithinGraceIsFree(): void
    {
        $service = new ReservationPricingService();
        $locker = $this->locker(1200);

        $result = $service->computeOvertime($locker, $this->at('2026-07-01T12:00:00Z'), $this->at('2026-07-01T12:04:00Z'));

        self::assertSame(4, $result['overtimeMinutes']);
        self::assertSame(0, $result['overtimeAmountCents']);
    }

    public function testOvertimeIsBilledInSteps(): void
    {
        $service = new ReservationPricingService();
        $locker = $this->locker(1200); // step price = ceil(1200/4) = 300

        // 20 min over - 5 min grace = 15 billable = 1 step
        $result = $service->computeOvertime($locker, $this->at('2026-07-01T12:00:00Z'), $this->at('2026-07-01T12:20:00Z'));

        self::assertSame(20, $result['overtimeMinutes']);
        self::assertSame(300, $result['overtimeAmountCents']);
    }

    public function testOvertimeAppliesBaySurcharge(): void
    {
        $service = new ReservationPricingService();
        $locker = $this->locker(1200, 50); // +50%

        $result = $service->computeOvertime($locker, $this->at('2026-07-01T12:00:00Z'), $this->at('2026-07-01T12:20:00Z'));

        self::assertSame(450, $result['overtimeAmountCents']); // 300 * 1.5
    }
}
