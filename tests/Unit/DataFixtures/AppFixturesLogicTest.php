<?php

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Locker;
use App\Entity\LockerBay;
use App\Entity\Reservation;
use App\Enum\LockerStatus;
use App\Enum\ReservationStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixturesLogicTest extends TestCase
{
    private AppFixtures $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = new AppFixtures($this->createMock(UserPasswordHasherInterface::class));
    }

    public function testResolveFixturesProfileSupportsLightFullAndFallback(): void
    {
        unset($_SERVER['FIXTURES_MODE'], $_ENV['FIXTURES_MODE']);
        putenv('FIXTURES_MODE');

        self::assertSame('light', $this->invoke('resolveFixturesProfile')['mode']);

        $_SERVER['FIXTURES_MODE'] = 'full';
        self::assertSame('full', $this->invoke('resolveFixturesProfile')['mode']);

        $_SERVER['FIXTURES_MODE'] = 'invalid';
        self::assertSame('light', $this->invoke('resolveFixturesProfile')['mode']);
    }

    public function testRandomLockerStatusCoversEveryBucket(): void
    {
        self::assertSame(LockerStatus::OFFLINE, $this->invoke('randomLockerStatus', new FakeFixturesFaker([5])));
        self::assertSame(LockerStatus::OUT_OF_ORDER, $this->invoke('randomLockerStatus', new FakeFixturesFaker([15])));
        self::assertSame(LockerStatus::OCCUPIED, $this->invoke('randomLockerStatus', new FakeFixturesFaker([30])));
        self::assertSame(LockerStatus::RESERVED, $this->invoke('randomLockerStatus', new FakeFixturesFaker([50])));
        self::assertSame(LockerStatus::AVAILABLE, $this->invoke('randomLockerStatus', new FakeFixturesFaker([80])));
    }

    public function testLastSeenAndReservationWindowBranches(): void
    {
        $now = new \DateTimeImmutable('2024-01-01 12:00:00');

        self::assertLessThan($now, $this->invoke('lastSeenForStatus', LockerStatus::OFFLINE, $now, new FakeFixturesFaker([4])));
        self::assertLessThan($now, $this->invoke('lastSeenForStatus', LockerStatus::OUT_OF_ORDER, $now, new FakeFixturesFaker([2])));
        self::assertLessThan($now, $this->invoke('lastSeenForStatus', LockerStatus::AVAILABLE, $now, new FakeFixturesFaker([10])));

        [, , $pastStatus] = $this->invoke('randomReservationWindow', $now, new FakeFixturesFaker([10, 2, 1, 60], [ReservationStatus::COMPLETED]));
        self::assertSame(ReservationStatus::COMPLETED, $pastStatus);

        [, , $futureStatus] = $this->invoke('randomReservationWindow', $now, new FakeFixturesFaker([80, 1, 1, 60], [], [true]));
        self::assertSame(ReservationStatus::CONFIRMED, $futureStatus);

        [, , $activeStatus] = $this->invoke('randomReservationWindow', $now, new FakeFixturesFaker([95, 30, 30]));
        self::assertSame(ReservationStatus::ACTIVE, $activeStatus);
    }

    public function testPaymentMappingAndPlannedAmount(): void
    {
        $startsAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $endsAt = new \DateTimeImmutable('2024-01-01 11:30:00');

        self::assertSame(1500, $this->invoke('computePlannedAmountCents', 1000, $startsAt, $endsAt));
        self::assertSame('requires_payment_method', $this->invoke('mapPaymentStatusFromReservationStatus', ReservationStatus::PENDING));
        self::assertSame('succeeded', $this->invoke('mapPaymentStatusFromReservationStatus', ReservationStatus::CONFIRMED));
        self::assertSame('succeeded', $this->invoke('mapPaymentStatusFromReservationStatus', ReservationStatus::ACTIVE));
        self::assertSame('succeeded', $this->invoke('mapPaymentStatusFromReservationStatus', ReservationStatus::COMPLETED));
        self::assertSame('canceled', $this->invoke('mapPaymentStatusFromReservationStatus', ReservationStatus::CANCELLED));
        self::assertSame('payment_failed', $this->invoke('mapPaymentStatusFromReservationStatus', ReservationStatus::EXPIRED));
    }

    public function testHydrateOvertimeFixtureDataBranches(): void
    {
        $lockerBay = (new LockerBay())->setOvertimeSurchargePercent(20);
        $locker = (new Locker())->setPriceCents(1200)->setLockerBay($lockerBay);
        $reservation = (new Reservation())
            ->setEndsAt(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $this->invoke('hydrateOvertimeFixtureData', $reservation, $locker, ReservationStatus::ACTIVE, new FakeFixturesFaker());
        self::assertNull($reservation->getActualEndsAt());

        $this->invoke('hydrateOvertimeFixtureData', $reservation, $locker, ReservationStatus::COMPLETED, new FakeFixturesFaker([], [], [false]));
        self::assertSame($reservation->getEndsAt(), $reservation->getActualEndsAt());

        $billable = (new Reservation())->setEndsAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $this->invoke('hydrateOvertimeFixtureData', $billable, $locker, ReservationStatus::COMPLETED, new FakeFixturesFaker([30, 123456], [], [true, true]));

        self::assertSame(30, $billable->getOvertimeMinutes());
        self::assertGreaterThan(0, $billable->getOvertimeAmountCents());
        self::assertSame('pi_over_fixture_123456', $billable->getOvertimePaymentIntentId());
        self::assertSame('succeeded', $billable->getOvertimePaymentStatus());
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod(AppFixtures::class, $method);

        return $reflection->invoke($this->fixtures, ...$args);
    }
}

final class FakeFixturesFaker
{
    /**
     * @param list<int> $numbers
     * @param list<mixed> $elements
     * @param list<bool> $booleans
     */
    public function __construct(
        private array $numbers = [],
        private array $elements = [],
        private array $booleans = [],
    ) {
    }

    public function numberBetween(int $min = 0, int $max = 100): int
    {
        return array_shift($this->numbers) ?? $min;
    }

    public function randomElement(array $values): mixed
    {
        return array_shift($this->elements) ?? $values[0];
    }

    public function boolean(int $chance = 50): bool
    {
        return array_shift($this->booleans) ?? false;
    }
}
