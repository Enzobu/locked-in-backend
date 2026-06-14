<?php

namespace App\Tests\Functional;

use App\Entity\Locker;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe webhook: payment confirmation/failure sync the reservation and locker,
 * handlers are idempotent and the signature is enforced.
 */
final class StripeWebhookTest extends AbstractApiTestCase
{
    private function webhookSecret(): string
    {
        return $_ENV['STRIPE_WEBHOOK_SECRET'] ?? 'whsec_dev_dummy_secret';
    }

    /**
     * @param array<string, mixed> $event
     */
    private function postWebhook(array $event, bool $validSignature = true): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = $validSignature
            ? hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret())
            : 'deadbeef';

        return $this->client->request('POST', '/api/stripe/webhook', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => sprintf('t=%d,v1=%s', $timestamp, $signature),
            ],
            'body' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $type, string $piId, string $status, int $reservationId): array
    {
        return ['type' => $type, 'data' => ['object' => [
            'id' => $piId,
            'status' => $status,
            'metadata' => ['flow' => 'initial', 'reservation_id' => (string) $reservationId],
        ]]];
    }

    private function seedPendingReservation(string $token, Locker $locker, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return $this->authedRequest('POST', '/api/reservations', $token, [
            'locker' => '/api/lockers/'.$locker->getId(),
            'startsAt' => $this->iso($start),
            'endsAt' => $this->iso($end),
        ])->toArray()['id'];
    }

    private function reservation(int $id): Reservation
    {
        $this->em->clear();

        return $this->em->find(Reservation::class, $id);
    }

    private function locker(int $id): Locker
    {
        return $this->em->find(Locker::class, $id);
    }

    public function testSucceededConfirmsReservationAndReservesLocker(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);
        $id = $this->seedPendingReservation($token, $locker, $this->future(1, 10), $this->future(1, 12));

        $response = $this->postWebhook($this->event('payment_intent.succeeded', 'pi_1', 'succeeded', $id));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $reservation = $this->reservation($id);
        self::assertSame(ReservationStatus::CONFIRMED, $reservation->getStatus());
        self::assertSame('succeeded', $reservation->getPaymentStatus());
        self::assertSame('reserved', $this->locker($locker->getId())->getStatus()->value);
    }

    public function testWebhookIsIdempotent(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);
        $id = $this->seedPendingReservation($token, $locker, $this->future(1, 10), $this->future(1, 12));

        $this->postWebhook($this->event('payment_intent.succeeded', 'pi_1', 'succeeded', $id));
        $second = $this->postWebhook($this->event('payment_intent.succeeded', 'pi_1', 'succeeded', $id));

        self::assertSame(Response::HTTP_OK, $second->getStatusCode());
        self::assertSame(ReservationStatus::CONFIRMED, $this->reservation($id)->getStatus());
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);
        $id = $this->seedPendingReservation($token, $locker, $this->future(1, 10), $this->future(1, 12));

        $response = $this->postWebhook($this->event('payment_intent.succeeded', 'pi_1', 'succeeded', $id), validSignature: false);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testPaymentFailedCancelsAndFreesLocker(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);
        $id = $this->seedPendingReservation($token, $locker, $this->future(1, 10), $this->future(1, 12));

        $response = $this->postWebhook($this->event('payment_intent.payment_failed', 'pi_1', 'requires_payment_method', $id));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(ReservationStatus::CANCELLED, $this->reservation($id)->getStatus());
        self::assertSame('available', $this->locker($locker->getId())->getStatus()->value);
    }

    public function testLockerBecomesOccupiedWhenConfirmedSlotCoversNow(): void
    {
        $customer = $this->createCustomer();
        $locker = $this->createLocker();
        $token = $this->tokenFor($customer);
        $id = $this->seedPendingReservation($token, $locker, $this->future(30, 10), $this->future(30, 12));

        // Move the slot so it spans "now", then confirm it.
        $this->em->clear();
        $reservation = $this->em->find(Reservation::class, $id);
        $reservation->setStartsAt(new \DateTimeImmutable('-1 hour'));
        $reservation->setEndsAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        $this->postWebhook($this->event('payment_intent.succeeded', 'pi_1', 'succeeded', $id));

        self::assertSame('occupied', $this->locker($locker->getId())->getStatus()->value);
    }

    public function testUnknownReservationIsAcked(): void
    {
        $this->createCustomer();
        $response = $this->postWebhook($this->event('payment_intent.succeeded', 'pi_x', 'succeeded', 99999999));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
