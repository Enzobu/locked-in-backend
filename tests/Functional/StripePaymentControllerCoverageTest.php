<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use App\Enum\LockerStatus;
use App\Enum\ReservationStatus;
use Symfony\Component\HttpFoundation\Response;

final class StripePaymentControllerCoverageTest extends AbstractApiTestCase
{
    public function testCreateIntentRejectsInvalidInputs(): void
    {
        $token = $this->tokenFor($this->createCustomer('payment-errors@test.com', 'password'));

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->authedRequest('POST', '/api/payments/intents', $token, [])->getStatusCode()
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->authedRequest('POST', '/api/payments/intents', $token, [
                'locker' => '/api/lockers/999999',
                'startsAt' => $this->future(1)->format(\DateTimeInterface::ATOM),
                'endsAt' => $this->future(1, 11)->format(\DateTimeInterface::ATOM),
            ])->getStatusCode()
        );

        $locker = $this->createLocker();
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->authedRequest('POST', '/api/payments/intents', $token, [
                'locker' => '/api/lockers/'.$locker->getId(),
                'startsAt' => 'bad-date',
                'endsAt' => $this->future(1, 11)->format(\DateTimeInterface::ATOM),
            ])->getStatusCode()
        );
    }

    public function testCreateIntentReusesExistingPendingReservation(): void
    {
        $customer = $this->createCustomer('payment-reuse@test.com', 'password');
        $locker = $this->createLocker(LockerStatus::AVAILABLE);
        $startsAt = $this->future(2, 10);
        $endsAt = $this->future(2, 12);
        $reservation = (new Reservation())
            ->setCustomer($customer)
            ->setLocker($locker)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setStatus(ReservationStatus::PENDING)
            ->setCurrency('eur')
            ->setPlannedAmountCents(2400)
            ->setPaymentStatus('requires_payment_method')
            ->setPaymentIntentId('pi_existing');
        $this->em->persist($reservation);
        $this->em->flush();

        $response = $this->authedRequest('POST', '/api/payments/intents', $this->tokenFor($customer), [
            'locker' => '/api/lockers/'.$locker->getId(),
            'startsAt' => $startsAt->format(\DateTimeInterface::ATOM),
            'endsAt' => $endsAt->format(\DateTimeInterface::ATOM),
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($response->toArray()['idempotentReuse']);
    }

    public function testCreateIntentBooksSlotThenReportsStripeFailure(): void
    {
        $customer = $this->createCustomer('payment-create@test.com', 'password');
        $locker = $this->createLocker(LockerStatus::AVAILABLE);
        $startsAt = $this->future(4, 10);
        $endsAt = $this->future(4, 12);

        $response = $this->authedRequest('POST', '/api/payments/intents', $this->tokenFor($customer), [
            'locker' => (string) $locker->getId(),
            'startsAt' => $startsAt->format(\DateTimeInterface::ATOM),
            'endsAt' => $endsAt->format(\DateTimeInterface::ATOM),
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Unable to create payment intent.', $response->toArray(false)['message']);
    }

    public function testCloseReservationSuccessAndValidationBranches(): void
    {
        $customer = $this->createCustomer('close@test.com', 'password');
        $other = $this->createCustomer('close-other@test.com', 'password');
        $locker = $this->createLocker(LockerStatus::OCCUPIED);
        $reservation = (new Reservation())
            ->setCustomer($customer)
            ->setLocker($locker)
            ->setStartsAt(new \DateTimeImmutable('-2 hours'))
            ->setEndsAt(new \DateTimeImmutable('+1 hour'))
            ->setStatus(ReservationStatus::ACTIVE)
            ->setCurrency('eur')
            ->setPlannedAmountCents(1200)
            ->setPaymentStatus('succeeded');
        $this->em->persist($reservation);
        $this->em->flush();

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->authedRequest('POST', '/api/reservations/'.$reservation->getId().'/close', $this->tokenFor($other), [])->getStatusCode()
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->authedRequest('POST', '/api/reservations/'.$reservation->getId().'/close', $this->tokenFor($customer), [
                'actualEndsAt' => 'not-a-date',
            ])->getStatusCode()
        );

        $response = $this->authedRequest('POST', '/api/reservations/'.$reservation->getId().'/close', $this->tokenFor($customer), [
            'actualEndsAt' => (new \DateTimeImmutable('+30 minutes'))->format(\DateTimeInterface::ATOM),
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(ReservationStatus::COMPLETED->value, $response->toArray()['status']);
    }

    public function testCloseReservationReportsOvertimePaymentFailure(): void
    {
        $customer = $this->createCustomer('close-overtime@test.com', 'password');
        $locker = $this->createLocker(LockerStatus::OCCUPIED);
        $reservation = (new Reservation())
            ->setCustomer($customer)
            ->setLocker($locker)
            ->setStartsAt(new \DateTimeImmutable('-3 hours'))
            ->setEndsAt(new \DateTimeImmutable('-1 hour'))
            ->setStatus(ReservationStatus::ACTIVE)
            ->setCurrency('eur')
            ->setPlannedAmountCents(1200)
            ->setPaymentStatus('succeeded');
        $this->em->persist($reservation);
        $this->em->flush();

        $response = $this->authedRequest('POST', '/api/reservations/'.$reservation->getId().'/close', $this->tokenFor($customer), [
            'actualEndsAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        self::assertSame(Response::HTTP_PAYMENT_REQUIRED, $response->getStatusCode());
        self::assertSame('failed', $response->toArray(false)['status']);
    }
}
