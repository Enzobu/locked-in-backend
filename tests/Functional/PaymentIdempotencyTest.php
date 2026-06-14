<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use Symfony\Component\HttpFoundation\Response;

/**
 * A duplicate POST /api/payments/intents reuses the existing PENDING hold instead
 * of creating a second reservation/charge.
 *
 * Stripe is not configured in tests, so the first call persists the hold then
 * fails at the Stripe call (400); the second identical call must reuse it (200).
 */
final class PaymentIdempotencyTest extends AbstractApiTestCase
{
    public function testDuplicateIntentReusesTheHold(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);

        $payload = [
            'locker' => '/api/lockers/'.$locker->getId(),
            'startsAt' => $this->iso($this->future(1, 10)),
            'endsAt' => $this->iso($this->future(1, 12)),
        ];

        $first = $this->authedRequest('POST', '/api/payments/intents', $token, $payload);
        self::assertSame(Response::HTTP_BAD_REQUEST, $first->getStatusCode());

        $second = $this->authedRequest('POST', '/api/payments/intents', $token, $payload);
        self::assertSame(Response::HTTP_OK, $second->getStatusCode());
        self::assertTrue($second->toArray()['idempotentReuse'] ?? false);

        $this->em->clear();
        $count = (int) $this->em->getRepository(Reservation::class)
            ->createQueryBuilder('r')->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        self::assertSame(1, $count, 'Only one reservation should have been persisted.');
    }
}
