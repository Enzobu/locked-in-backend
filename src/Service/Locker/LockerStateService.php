<?php

namespace App\Service\Locker;

use App\Entity\Locker;
use App\Enum\LockerStatus;
use App\Repository\ReservationRepository;

/**
 * Keeps a locker's status in sync with its reservations.
 *
 * OUT_OF_ORDER and OFFLINE are hardware-driven states: they are sticky and only
 * cleared by an operator, never by reservation activity. Every other state is
 * derived from the reservations attached to the locker.
 */
final class LockerStateService
{
    private const STICKY_STATUSES = [
        LockerStatus::OUT_OF_ORDER,
        LockerStatus::OFFLINE,
    ];

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function recompute(Locker $locker, ?\DateTimeImmutable $now = null): void
    {
        if (in_array($locker->getStatus(), self::STICKY_STATUSES, true)) {
            return;
        }

        $now ??= new \DateTimeImmutable();

        if ($this->reservationRepository->hasOccupyingReservation($locker, $now)) {
            $locker->setStatus(LockerStatus::OCCUPIED);

            return;
        }

        if ($this->reservationRepository->hasActiveReservation($locker, $now)) {
            $locker->setStatus(LockerStatus::RESERVED);

            return;
        }

        $locker->setStatus(LockerStatus::AVAILABLE);
    }
}
