<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Locker;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use App\Repository\LockerRepository;
use App\Repository\ReservationRepository;
use App\Service\Reservation\ReservationAvailabilityChecker;
use App\Service\Reservation\ReservationLifecycleService;
use App\Service\ReservationPricingService;
use App\Service\StripeClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiStripePaymentController extends AbstractController
{
    #[Route('/payments/intents', name: 'api_payments_create_intent', methods: ['POST'])]
    public function createIntent(
        Request $request,
        LockerRepository $lockerRepository,
        ReservationRepository $reservationRepository,
        ReservationPricingService $pricingService,
        StripeClient $stripeClient,
        EntityManagerInterface $entityManager,
        ReservationAvailabilityChecker $availabilityChecker,
    ): JsonResponse {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $lockerReference = $payload['locker'] ?? null;
        $startsAtRaw = $payload['startsAt'] ?? null;
        $endsAtRaw = $payload['endsAt'] ?? null;

        if (!is_string($lockerReference) || !is_string($startsAtRaw) || !is_string($endsAtRaw)) {
            return $this->json(['message' => 'locker, startsAt and endsAt are required.'], Response::HTTP_BAD_REQUEST);
        }

        $locker = $this->resolveLocker($lockerReference, $lockerRepository);
        if (!$locker instanceof Locker) {
            return $this->json(['message' => 'Locker not found.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $startsAt = new \DateTimeImmutable($startsAtRaw);
            $endsAt = new \DateTimeImmutable($endsAtRaw);
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid startsAt or endsAt format.'], Response::HTTP_BAD_REQUEST);
        }

        // Idempotency: if an identical PENDING hold already exists for this customer,
        // locker and slot, reuse it instead of creating a duplicate reservation/charge.
        $existing = $reservationRepository->findReusablePending($customer, $locker, $startsAt, $endsAt);
        if ($existing instanceof Reservation) {
            $clientSecret = null;
            $existingIntentId = $existing->getPaymentIntentId();
            if (is_string($existingIntentId) && $existingIntentId !== '') {
                try {
                    $clientSecret = $stripeClient->retrievePaymentIntent($existingIntentId)['client_secret'] ?? null;
                } catch (\Throwable) {
                    $clientSecret = null;
                }
            }

            return $this->json([
                'reservationId' => $existing->getId(),
                'amountCents' => $existing->getPlannedAmountCents(),
                'currency' => $existing->getCurrency(),
                'paymentIntentId' => $existing->getPaymentIntentId(),
                'clientSecret' => $clientSecret,
                'status' => $existing->getPaymentStatus(),
                'idempotentReuse' => true,
            ], Response::HTTP_OK);
        }

        $plannedAmountCents = $pricingService->computePlannedAmountCents($locker, $startsAt, $endsAt);
        if ($plannedAmountCents <= 0) {
            return $this->json(['message' => 'Invalid computed payment amount.'], Response::HTTP_BAD_REQUEST);
        }

        $reservation = (new Reservation())
            ->setCustomer($customer)
            ->setLocker($locker)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setStatus(ReservationStatus::PENDING)
            ->setCurrency('eur')
            ->setPlannedAmountCents($plannedAmountCents)
            ->setPaymentStatus('requires_payment_method');

        // Reserve the slot atomically BEFORE talking to Stripe: lock the locker row,
        // reject overlaps / invalid durations / unavailable lockers, then persist the
        // PENDING reservation. Committing here books the slot and prevents double-booking.
        try {
            $entityManager->wrapInTransaction(function () use ($availabilityChecker, $locker, $startsAt, $endsAt, $reservation, $entityManager): void {
                $availabilityChecker->lockLocker($locker);
                $availabilityChecker->assertBookable($locker, $startsAt, $endsAt);
                $entityManager->persist($reservation);
            });
        } catch (HttpException $e) {
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        try {
            $stripeCustomerId = $this->ensureStripeCustomer($customer, $stripeClient, $entityManager);

            $intent = $stripeClient->createPaymentIntent([
                'amount' => $plannedAmountCents,
                'currency' => 'eur',
                'customer' => $stripeCustomerId,
                'automatic_payment_methods[enabled]' => 'true',
                'setup_future_usage' => 'off_session',
                'metadata[reservation_id]' => (string) $reservation->getId(),
                'metadata[flow]' => 'initial',
                'metadata[customer_id]' => (string) $customer->getId(),
                'metadata[locker_id]' => (string) $locker->getId(),
            ], 'pi-resv-'.$reservation->getId());

            $reservation
                ->setPaymentIntentId($intent['id'] ?? null)
                ->setPaymentStatus((string) ($intent['status'] ?? 'requires_payment_method'));

            $entityManager->flush();

            return $this->json([
                'reservationId' => $reservation->getId(),
                'amountCents' => $plannedAmountCents,
                'currency' => $reservation->getCurrency(),
                'paymentIntentId' => $reservation->getPaymentIntentId(),
                'clientSecret' => $intent['client_secret'] ?? null,
                'status' => $reservation->getPaymentStatus(),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Unable to create payment intent.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/reservations/{id}/close', name: 'api_reservations_close', methods: ['POST'])]
    public function closeReservation(
        Reservation $reservation,
        Request $request,
        ReservationPricingService $pricingService,
        StripeClient $stripeClient,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($reservation->getCustomer()?->getId() !== $customer->getId()) {
            return $this->json(['message' => 'Forbidden reservation access.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $request->getContent() !== '' ? $request->toArray() : [];
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $actualEndsAt = new \DateTimeImmutable();
        if (isset($payload['actualEndsAt']) && is_string($payload['actualEndsAt'])) {
            try {
                $actualEndsAt = new \DateTimeImmutable($payload['actualEndsAt']);
            } catch (\Throwable) {
                return $this->json(['message' => 'Invalid actualEndsAt format.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $locker = $reservation->getLocker();
        $plannedEndsAt = $reservation->getEndsAt();
        if (!$locker instanceof Locker || !$plannedEndsAt instanceof \DateTimeImmutable) {
            return $this->json(['message' => 'Reservation data is incomplete.'], Response::HTTP_BAD_REQUEST);
        }

        $overtime = $pricingService->computeOvertime($locker, $plannedEndsAt, $actualEndsAt);

        $reservation
            ->setActualEndsAt($actualEndsAt)
            ->setOvertimeMinutes($overtime['overtimeMinutes'])
            ->setOvertimeAmountCents($overtime['overtimeAmountCents'])
            ->setStatus(ReservationStatus::COMPLETED)
            ->setOvertimePaymentStatus($overtime['overtimeAmountCents'] > 0 ? 'pending' : 'none');

        if ($overtime['overtimeAmountCents'] > 0) {
            try {
                $stripeCustomerId = $this->ensureStripeCustomer($customer, $stripeClient, $entityManager);
                $intent = $stripeClient->createPaymentIntent([
                    'amount' => $overtime['overtimeAmountCents'],
                    'currency' => $reservation->getCurrency(),
                    'customer' => $stripeCustomerId,
                    'confirm' => 'true',
                    'off_session' => 'true',
                    'metadata[reservation_id]' => (string) $reservation->getId(),
                    'metadata[flow]' => 'overtime',
                    'metadata[customer_id]' => (string) $customer->getId(),
                ]);

                $reservation
                    ->setOvertimePaymentIntentId($intent['id'] ?? null)
                    ->setOvertimePaymentStatus((string) ($intent['status'] ?? 'processing'));
            } catch (\Throwable $e) {
                $reservation->setOvertimePaymentStatus('failed');
                $entityManager->flush();

                return $this->json([
                    'reservationId' => $reservation->getId(),
                    'message' => 'Overtime detected but off-session charge failed.',
                    'overtimeMinutes' => $overtime['overtimeMinutes'],
                    'overtimeAmountCents' => $overtime['overtimeAmountCents'],
                    'status' => $reservation->getOvertimePaymentStatus(),
                    'error' => $e->getMessage(),
                ], Response::HTTP_PAYMENT_REQUIRED);
            }
        }

        $entityManager->flush();

        return $this->json([
            'reservationId' => $reservation->getId(),
            'status' => $reservation->getStatus()->value,
            'actualEndsAt' => $reservation->getActualEndsAt()?->format(\DateTimeInterface::ATOM),
            'overtimeMinutes' => $reservation->getOvertimeMinutes(),
            'overtimeAmountCents' => $reservation->getOvertimeAmountCents(),
            'overtimePaymentStatus' => $reservation->getOvertimePaymentStatus(),
            'overtimePaymentIntentId' => $reservation->getOvertimePaymentIntentId(),
        ]);
    }

    #[Route('/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function webhook(
        Request $request,
        StripeClient $stripeClient,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager,
        ReservationLifecycleService $lifecycleService,
    ): JsonResponse {
        $payload = $request->getContent();
        $signatureHeader = $request->headers->get('Stripe-Signature');

        try {
            $event = $stripeClient->verifyAndDecodeWebhook($payload, $signatureHeader);
        } catch (\Throwable $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $eventType = $event['type'] ?? null;
        $object = $event['data']['object'] ?? null;
        if (!is_string($eventType) || !is_array($object)) {
            return $this->json(['message' => 'Invalid webhook event format.'], Response::HTTP_BAD_REQUEST);
        }

        $paymentIntentId = $object['id'] ?? null;
        if (!is_string($paymentIntentId) || $paymentIntentId === '') {
            return $this->json(['message' => 'Missing payment intent id.'], Response::HTTP_BAD_REQUEST);
        }

        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $flow = (string) ($metadata['flow'] ?? '');
        $reservationId = isset($metadata['reservation_id']) ? (int) $metadata['reservation_id'] : null;

        $reservation = null;
        if ($reservationId !== null && $reservationId > 0) {
            $reservation = $reservationRepository->find($reservationId);
        }

        if (!$reservation instanceof Reservation) {
            $reservation = $reservationRepository->findOneBy(['paymentIntentId' => $paymentIntentId])
                ?? $reservationRepository->findOneBy(['overtimePaymentIntentId' => $paymentIntentId]);
        }

        if (!$reservation instanceof Reservation) {
            return $this->json(['message' => 'Reservation not found for payment intent.'], Response::HTTP_OK);
        }

        if ($flow === '') {
            $flow = $reservation->getPaymentIntentId() === $paymentIntentId ? 'initial' : 'overtime';
        }

        $status = (string) ($object['status'] ?? 'unknown');

        // Only react to the payment-intent lifecycle events we care about; ignore
        // everything else (Stripe sends many event types) but still ack with 200 so
        // Stripe stops retrying. All handlers below are idempotent on retries.
        if ($eventType === 'payment_intent.succeeded') {
            if ($flow === 'initial') {
                $lifecycleService->confirmPaid($reservation);
            } else {
                $reservation->setOvertimePaymentStatus($status);
            }
            $entityManager->flush();
        } elseif (in_array($eventType, ['payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
            if ($flow === 'initial') {
                $lifecycleService->markPaymentFailed($reservation, $status);
            } else {
                $reservation->setOvertimePaymentStatus($status);
            }
            $entityManager->flush();
        }

        return $this->json(['received' => true], Response::HTTP_OK);
    }

    private function resolveLocker(string $reference, LockerRepository $lockerRepository): ?Locker
    {
        if (is_numeric($reference)) {
            return $lockerRepository->find((int) $reference);
        }

        if (preg_match('#^/api/lockers/(\d+)$#', $reference, $matches) === 1) {
            return $lockerRepository->find((int) $matches[1]);
        }

        return null;
    }

    private function ensureStripeCustomer(Customer $customer, StripeClient $stripeClient, EntityManagerInterface $entityManager): string
    {
        if (is_string($customer->getStripeCustomerId()) && $customer->getStripeCustomerId() !== '') {
            return $customer->getStripeCustomerId();
        }

        $stripeCustomer = $stripeClient->createCustomer(
            (string) $customer->getEmail(),
            trim((string) $customer->getFirstname().' '.(string) $customer->getLastname())
        );

        $stripeCustomerId = $stripeCustomer['id'] ?? null;
        if (!is_string($stripeCustomerId) || $stripeCustomerId === '') {
            throw new \RuntimeException('Unable to create Stripe customer.');
        }

        $customer->setStripeCustomerId($stripeCustomerId);
        $entityManager->flush();

        return $stripeCustomerId;
    }
}
