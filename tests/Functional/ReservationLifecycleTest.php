<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cancellation lifecycle: frees the slot, blocks illegal transitions.
 */
final class ReservationLifecycleTest extends AbstractApiTestCase
{
    private const MERGE_PATCH = 'application/merge-patch+json';

    private function book(string $token, int $lockerId, \DateTimeImmutable $start, \DateTimeImmutable $end): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->authedRequest('POST', '/api/reservations', $token, [
            'locker' => '/api/lockers/'.$lockerId,
            'startsAt' => $this->iso($start),
            'endsAt' => $this->iso($end),
        ]);
    }

    public function testCancellationFreesTheSlot(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $a = $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12))->toArray();

        self::assertSame(
            Response::HTTP_CONFLICT,
            $this->book($token, $locker->getId(), $this->future(1, 11), $this->future(1, 13))->getStatusCode(),
        );

        $patch = $this->authedRequest('PATCH', '/api/reservations/'.$a['id'], $token, ['status' => 'cancelled'], self::MERGE_PATCH);
        self::assertSame(Response::HTTP_OK, $patch->getStatusCode());
        $body = $patch->toArray();
        self::assertSame('cancelled', $body['status']);
        self::assertSame('none', $body['refundStatus']);
        self::assertNotNull($body['cancelledAt']);

        self::assertSame(
            Response::HTTP_CREATED,
            $this->book($token, $locker->getId(), $this->future(1, 11), $this->future(1, 13))->getStatusCode(),
        );
    }

    public function testCustomerCannotSelfConfirm(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);
        $a = $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12))->toArray();

        $patch = $this->authedRequest('PATCH', '/api/reservations/'.$a['id'], $token, ['status' => 'confirmed'], self::MERGE_PATCH);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $patch->getStatusCode());
    }

    public function testCannotCancelAnActiveReservation(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);
        $a = $this->book($token, $locker->getId(), $this->future(1, 10), $this->future(1, 12))->toArray();

        // Force the reservation into ACTIVE, which is not a cancellable state.
        $this->em->clear();
        $reservation = $this->em->find(Reservation::class, $a['id']);
        $reservation->setStatus(ReservationStatus::ACTIVE);
        $this->em->flush();

        $patch = $this->authedRequest('PATCH', '/api/reservations/'.$a['id'], $token, ['status' => 'cancelled'], self::MERGE_PATCH);

        self::assertSame(Response::HTTP_CONFLICT, $patch->getStatusCode());
    }
}
