<?php

namespace App\Tests\Functional;

use App\Enum\LockerStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * Booking guard: anti double-booking, min/max duration, unbookable lockers.
 */
final class ReservationGuardTest extends AbstractApiTestCase
{
    private function book(string $token, int $lockerId, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return $this->authedRequest('POST', '/api/reservations', $token, [
            'locker' => '/api/lockers/'.$lockerId,
            'startsAt' => $this->iso($start),
            'endsAt' => $this->iso($end),
        ])->getStatusCode();
    }

    public function testValidBookingIsAccepted(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $status = $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12));

        self::assertSame(Response::HTTP_CREATED, $status);
    }

    public function testOverlappingBookingIsRejected(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12));
        $status = $this->book($token, $locker->getId(), $this->future(1, 11), $this->future(1, 13));

        self::assertSame(Response::HTTP_CONFLICT, $status);
    }

    public function testAdjacentBookingIsAccepted(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12));
        $status = $this->book($token, $locker->getId(), $this->future(1, 12), $this->future(1, 14));

        self::assertSame(Response::HTTP_CREATED, $status);
    }

    public function testTooShortDurationIsRejected(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker(minDuration: 60);
        $token = $this->tokenFor($customer);

        $status = $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 10)->modify('+30 minutes'));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
    }

    public function testTooLongDurationIsRejected(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker(maxDuration: 120);
        $token = $this->tokenFor($customer);

        $status = $this->book($token, $locker->getId(), $this->future(1, 8), $this->future(1, 14));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
    }

    public function testUnbookableLockerIsRejected(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker(status: LockerStatus::OUT_OF_ORDER);
        $token = $this->tokenFor($customer);

        $status = $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12));

        self::assertSame(Response::HTTP_CONFLICT, $status);
    }

    private function reschedule(string $token, int $reservationId, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return $this->authedRequest(
            'PATCH',
            '/api/reservations/'.$reservationId,
            $token,
            ['startsAt' => $this->iso($start), 'endsAt' => $this->iso($end)],
            'application/merge-patch+json',
        )->getStatusCode();
    }

    public function testReschedulingIntoAnOverlapIsRejected(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12));
        $b = $this->authedRequest('POST', '/api/reservations', $token, [
            'locker' => '/api/lockers/'.$locker->getId(),
            'startsAt' => $this->iso($this->future(1, 14)),
            'endsAt' => $this->iso($this->future(1, 16)),
        ])->toArray();

        // Move B onto A's window -> must be rejected (PATCH cannot bypass the guard).
        self::assertSame(
            Response::HTTP_CONFLICT,
            $this->reschedule($token, $b['id'], $this->future(1, 11), $this->future(1, 13)),
        );
    }

    public function testReschedulingIntoAFreeSlotIsAllowed(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $a = $this->authedRequest('POST', '/api/reservations', $token, [
            'locker' => '/api/lockers/'.$locker->getId(),
            'startsAt' => $this->iso($this->future(1, 10)),
            'endsAt' => $this->iso($this->future(1, 12)),
        ])->toArray();

        // Moving the reservation to a free window (only itself there) is fine.
        self::assertSame(
            Response::HTTP_OK,
            $this->reschedule($token, $a['id'], $this->future(1, 14), $this->future(1, 16)),
        );
    }
}
