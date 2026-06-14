<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;

/**
 * app:reservations:expire turns stale unpaid holds into EXPIRED and frees the locker.
 */
final class ReservationExpirationTest extends AbstractApiTestCase
{
    private function runExpire(int $olderThanMinutes): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:reservations:expire'));
        $tester->execute(['--older-than' => (string) $olderThanMinutes]);
        $tester->assertCommandIsSuccessful();
    }

    private function book(string $token, int $lockerId, \DateTimeImmutable $start, \DateTimeImmutable $end): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->authedRequest('POST', '/api/reservations', $token, [
            'locker' => '/api/lockers/'.$lockerId,
            'startsAt' => $this->iso($start),
            'endsAt' => $this->iso($end),
        ]);
    }

    public function testStalePendingIsExpiredAndSlotFreed(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $a = $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12))->toArray();

        $this->runExpire(0);

        $this->em->clear();
        self::assertSame(ReservationStatus::EXPIRED, $this->em->find(Reservation::class, $a['id'])->getStatus());

        self::assertSame(
            Response::HTTP_CREATED,
            $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12))->getStatusCode(),
        );
    }

    public function testFreshPendingIsNotExpired(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $a = $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12))->toArray();

        $this->runExpire(60); // only expire holds older than 1h; this one is brand new

        $this->em->clear();
        self::assertSame(ReservationStatus::PENDING, $this->em->find(Reservation::class, $a['id'])->getStatus());
    }
}
