<?php

namespace App\Repository;

use App\Entity\LockerBay;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LockerBay>
 */
class LockerBayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LockerBay::class);
    }

    /**
     * @return LockerBay[]
     */
    public function findVisibleForBackoffice(User $user, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.company', 'c')->addSelect('c')
            ->leftJoin('b.lockers', 'l')->addSelect('l')
            ->orderBy('b.name', 'ASC');

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $qb
                ->andWhere('b.company = :company')
                ->setParameter('company', $user->getCompany());
        }

        if ($search !== null && trim($search) !== '') {
            $qb
                ->andWhere('LOWER(b.name) LIKE :q OR LOWER(c.name) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($search)).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneVisibleForBackoffice(int $id, User $user): ?LockerBay
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.company', 'c')->addSelect('c')
            ->leftJoin('b.lockers', 'l')->addSelect('l')
            ->leftJoin('l.specification', 's')->addSelect('s')
            ->andWhere('b.id = :id')
            ->setParameter('id', $id);

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $qb
                ->andWhere('b.company = :company')
                ->setParameter('company', $user->getCompany());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
