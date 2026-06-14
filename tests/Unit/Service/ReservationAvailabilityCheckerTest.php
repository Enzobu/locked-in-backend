<?php

namespace App\Tests\Unit\Service;

use App\Entity\Locker;
use App\Entity\LockerBay;
use App\Entity\Reservation;
use App\Enum\LockerStatus;
use App\Repository\ReservationRepository;
use App\Service\Reservation\ReservationAvailabilityChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[CoversClass(ReservationAvailabilityChecker::class)]
final class ReservationAvailabilityCheckerTest extends TestCase
{
    private function checker(array $overlaps = []): ReservationAvailabilityChecker
    {
        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('findOverlapping')->willReturn($overlaps);

        return new ReservationAvailabilityChecker($repository, $this->createMock(EntityManagerInterface::class));
    }

    private function locker(LockerStatus $status = LockerStatus::AVAILABLE, ?int $min = 60, ?int $max = 720): Locker
    {
        $bay = (new LockerBay())->setMinDuration($min)->setMaxDuration($max);

        return (new Locker())->setStatus($status)->setLockerBay($bay);
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time);
    }

    public function testValidBookingPasses(): void
    {
        $this->expectNotToPerformAssertions();
        $this->checker()->assertBookable($this->locker(), $this->at('2026-07-01T10:00:00Z'), $this->at('2026-07-01T11:30:00Z'));
    }

    public function testOverlapIsRejected(): void
    {
        $this->expectException(ConflictHttpException::class);
        $this->checker([new Reservation()])
            ->assertBookable($this->locker(), $this->at('2026-07-01T10:00:00Z'), $this->at('2026-07-01T11:30:00Z'));
    }

    public function testOutOfOrderLockerIsRejected(): void
    {
        $this->expectException(ConflictHttpException::class);
        $this->checker()->assertBookable($this->locker(LockerStatus::OUT_OF_ORDER), $this->at('2026-07-01T10:00:00Z'), $this->at('2026-07-01T11:30:00Z'));
    }

    public function testTooShortIsRejected(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->checker()->assertBookable($this->locker(min: 60), $this->at('2026-07-01T10:00:00Z'), $this->at('2026-07-01T10:30:00Z'));
    }

    public function testTooLongIsRejected(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->checker()->assertBookable($this->locker(max: 120), $this->at('2026-07-01T10:00:00Z'), $this->at('2026-07-01T15:00:00Z'));
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->checker()->assertBookable($this->locker(), $this->at('2026-07-01T12:00:00Z'), $this->at('2026-07-01T10:00:00Z'));
    }
}
