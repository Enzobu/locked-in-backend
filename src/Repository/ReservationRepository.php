<?php

namespace App\Repository;

use App\Entity\Locker;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    /**
     * Statuses that occupy a time slot and therefore conflict with new bookings.
     */
    public const ACTIVE_STATUSES = [
        ReservationStatus::PENDING,
        ReservationStatus::CONFIRMED,
        ReservationStatus::ACTIVE,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Returns the active reservations on a locker that overlap the given time window.
     * Two windows overlap when existing.startsAt < new.endsAt AND existing.endsAt > new.startsAt.
     *
     * @return Reservation[]
     */
    public function findOverlapping(
        Locker $locker,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        ?int $excludeReservationId = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.locker = :locker')
            ->andWhere('r.status IN (:activeStatuses)')
            ->andWhere('r.startsAt < :endsAt')
            ->andWhere('r.endsAt > :startsAt')
            ->setParameter('locker', $locker)
            ->setParameter('activeStatuses', array_map(static fn (ReservationStatus $s): string => $s->value, self::ACTIVE_STATUSES))
            ->setParameter('startsAt', $startsAt)
            ->setParameter('endsAt', $endsAt);

        if ($excludeReservationId !== null) {
            $qb->andWhere('r.id != :excludeId')->setParameter('excludeId', $excludeReservationId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * True when a confirmed/active reservation currently occupies the locker (now within its window).
     */
    public function hasOccupyingReservation(Locker $locker, \DateTimeImmutable $now): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.locker = :locker')
            ->andWhere('r.status IN (:occupying)')
            ->andWhere('r.startsAt <= :now')
            ->andWhere('r.endsAt > :now')
            ->setParameter('locker', $locker)
            ->setParameter('occupying', [ReservationStatus::CONFIRMED->value, ReservationStatus::ACTIVE->value])
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * True when the locker still has a *paid* reservation that has not ended yet
     * (a current or upcoming confirmed commitment). PENDING holds are tentative
     * and intentionally do not reserve the locker.
     */
    public function hasActiveReservation(Locker $locker, \DateTimeImmutable $now): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.locker = :locker')
            ->andWhere('r.status IN (:confirmed)')
            ->andWhere('r.endsAt > :now')
            ->setParameter('locker', $locker)
            ->setParameter('confirmed', [ReservationStatus::CONFIRMED->value, ReservationStatus::ACTIVE->value])
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Returns PENDING reservations created before $threshold that were never paid,
     * so they can be expired and the locker freed.
     *
     * @return Reservation[]
     */
    public function findExpirablePending(\DateTimeImmutable $threshold, int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :pending')
            ->andWhere('r.createdAt < :threshold')
            ->setParameter('pending', ReservationStatus::PENDING->value)
            ->setParameter('threshold', $threshold)
            ->orderBy('r.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function findUpcomingForLocker(Locker $locker, \DateTimeImmutable $now, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.customer', 'c')->addSelect('c')
            ->andWhere('r.locker = :locker')
            ->andWhere('r.startsAt > :now')
            ->setParameter('locker', $locker)
            ->setParameter('now', $now)
            ->orderBy('r.startsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function findCurrentForLocker(Locker $locker, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.customer', 'c')->addSelect('c')
            ->andWhere('r.locker = :locker')
            ->andWhere('r.startsAt <= :now')
            ->andWhere('r.endsAt >= :now')
            ->setParameter('locker', $locker)
            ->setParameter('now', $now)
            ->orderBy('r.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function findHistoryForLocker(Locker $locker, \DateTimeImmutable $now, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.customer', 'c')->addSelect('c')
            ->andWhere('r.locker = :locker')
            ->andWhere('r.endsAt < :now')
            ->setParameter('locker', $locker)
            ->setParameter('now', $now)
            ->orderBy('r.endsAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
