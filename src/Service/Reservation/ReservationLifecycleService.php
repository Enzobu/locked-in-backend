<?php

namespace App\Service\Reservation;

use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use App\Service\Locker\LockerStateService;
use App\Service\StripeClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Owns the reservation state machine and the side effects of each transition
 * (refunds, freeing the locker). Centralised so every entry point — REST patch,
 * expiry cron, Stripe webhook — applies the exact same rules.
 */
final class ReservationLifecycleService
{
    /**
     * Statuses a reservation can be cancelled from.
     */
    public const CANCELLABLE_STATUSES = [
        ReservationStatus::PENDING,
        ReservationStatus::CONFIRMED,
    ];

    public function __construct(
        private readonly LockerStateService $lockerStateService,
        private readonly StripeClient $stripeClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Cancels a reservation: validates the transition, refunds the customer when
     * the cancellation policy allows it, and frees the locker.
     *
     * @throws ConflictHttpException when the reservation cannot be cancelled from its current status
     */
    public function cancel(Reservation $reservation): void
    {
        if (!in_array($reservation->getStatus(), self::CANCELLABLE_STATUSES, true)) {
            throw new ConflictHttpException(sprintf(
                'Reservation cannot be cancelled from status "%s".',
                $reservation->getStatus()->value,
            ));
        }

        $now = new \DateTimeImmutable();
        $this->refundIfApplicable($reservation, $now);

        $reservation
            ->setStatus(ReservationStatus::CANCELLED)
            ->setCancelledAt($now);

        $this->releaseLocker($reservation, $now);
    }

    /**
     * Expires an unpaid PENDING reservation and frees the locker. No-op otherwise.
     */
    public function expire(Reservation $reservation): bool
    {
        if ($reservation->getStatus() !== ReservationStatus::PENDING) {
            return false;
        }

        $reservation->setStatus(ReservationStatus::EXPIRED);
        $this->releaseLocker($reservation);

        return true;
    }

    /**
     * Marks a reservation as paid/confirmed and refreshes the locker status.
     * Never resurrects a reservation that was already cancelled or expired.
     */
    public function confirmPaid(Reservation $reservation): void
    {
        if (in_array($reservation->getStatus(), [ReservationStatus::CANCELLED, ReservationStatus::EXPIRED], true)) {
            return;
        }

        $reservation->setStatus(ReservationStatus::CONFIRMED);
        $reservation->setPaymentStatus('succeeded');
        $this->refreshLocker($reservation);
    }

    private function refundIfApplicable(Reservation $reservation, \DateTimeImmutable $now): void
    {
        $isPaid = $reservation->getPaymentStatus() === 'succeeded' && $reservation->getPaymentIntentId() !== null;
        if (!$isPaid) {
            $reservation->setRefundStatus('none');

            return;
        }

        // Cancellation policy: full refund only when cancelled before the slot starts.
        if ($reservation->getStartsAt() !== null && $now >= $reservation->getStartsAt()) {
            $reservation->setRefundStatus('not_applicable');

            return;
        }

        try {
            $refund = $this->stripeClient->createRefund((string) $reservation->getPaymentIntentId());
            $reservation
                ->setRefundId($refund['id'] ?? null)
                ->setRefundStatus((string) ($refund['status'] ?? 'pending'));
        } catch (\Throwable $e) {
            $this->logger->error('Refund failed for reservation {id}: {error}', [
                'id' => $reservation->getId(),
                'error' => $e->getMessage(),
            ]);
            $reservation->setRefundStatus('failed');
        }
    }

    private function releaseLocker(Reservation $reservation, ?\DateTimeImmutable $now = null): void
    {
        $locker = $reservation->getLocker();
        if ($locker !== null) {
            $this->lockerStateService->recompute($locker, $now);
        }
    }

    private function refreshLocker(Reservation $reservation): void
    {
        $this->releaseLocker($reservation);
    }
}
