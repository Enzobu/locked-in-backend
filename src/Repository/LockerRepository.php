<?php

namespace App\Repository;

use App\Entity\Locker;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Locker>
 */
class LockerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Locker::class);
    }

    public function findOneVisibleForBackoffice(int $id, User $user): ?Locker
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.lockerBay', 'b')->addSelect('b')
            ->leftJoin('b.company', 'c')->addSelect('c')
            ->leftJoin('l.specification', 's')->addSelect('s')
            ->andWhere('l.id = :id')
            ->setParameter('id', $id);

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $qb
                ->andWhere('b.company = :company')
                ->setParameter('company', $user->getCompany());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
