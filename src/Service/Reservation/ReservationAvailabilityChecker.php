<?php

namespace App\Service\Reservation;

use App\Entity\Locker;
use App\Enum\LockerStatus;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Centralises the business rules that decide whether a locker can be booked
 * for a given time window. Used by every reservation creation path so the
 * rules cannot be bypassed.
 */
final class ReservationAvailabilityChecker
{
    /**
     * Locker statuses that forbid any booking regardless of the time window.
     */
    private const UNBOOKABLE_STATUSES = [
        LockerStatus::OUT_OF_ORDER,
        LockerStatus::OFFLINE,
    ];

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Acquires a write lock on the locker row so concurrent bookings on the same
     * locker are serialised (prevents the double-booking race condition).
     * Must be called inside a transaction.
     */
    public function lockLocker(Locker $locker): void
    {
        $this->entityManager->lock($locker, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Validates a booking request. Throws an HTTP exception (mapped to the right
     * status code by API Platform / the controllers) when the slot is not bookable.
     *
     * @throws UnprocessableEntityHttpException on invalid input (422)
     * @throws ConflictHttpException            on unavailable locker or overlap (409)
     */
    public function assertBookable(
        Locker $locker,
        ?\DateTimeImmutable $startsAt,
        ?\DateTimeImmutable $endsAt,
        ?int $excludeReservationId = null,
    ): void {
        if (!$startsAt instanceof \DateTimeImmutable || !$endsAt instanceof \DateTimeImmutable) {
            throw new UnprocessableEntityHttpException('startsAt and endsAt are required.');
        }

        if ($endsAt <= $startsAt) {
            throw new UnprocessableEntityHttpException('endsAt must be greater than startsAt.');
        }

        if (in_array($locker->getStatus(), self::UNBOOKABLE_STATUSES, true)) {
            throw new ConflictHttpException(sprintf(
                'Locker %s is not available for booking (status: %s).',
                (string) $locker->getId(),
                $locker->getStatus()->value,
            ));
        }

        $this->assertDurationWithinBayBounds($locker, $startsAt, $endsAt);

        $conflicts = $this->reservationRepository->findOverlapping($locker, $startsAt, $endsAt, $excludeReservationId);
        if (\count($conflicts) > 0) {
            throw new ConflictHttpException('This locker is already booked for the selected time slot.');
        }
    }

    private function assertDurationWithinBayBounds(Locker $locker, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): void
    {
        $durationMinutes = intdiv($endsAt->getTimestamp() - $startsAt->getTimestamp(), 60);
        $bay = $locker->getLockerBay();

        $min = $bay?->getMinDuration();
        if ($min !== null && $durationMinutes < $min) {
            throw new UnprocessableEntityHttpException(sprintf('Reservation is too short: minimum duration is %d minutes.', $min));
        }

        $max = $bay?->getMaxDuration();
        if ($max !== null && $durationMinutes > $max) {
            throw new UnprocessableEntityHttpException(sprintf('Reservation is too long: maximum duration is %d minutes.', $max));
        }
    }
}
