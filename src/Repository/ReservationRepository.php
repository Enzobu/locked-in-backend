<?php

namespace App\Repository;

use App\Entity\Locker;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
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
