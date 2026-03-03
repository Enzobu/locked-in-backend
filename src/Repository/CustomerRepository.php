<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * @return Customer[]
     */
    public function searchByDeletionStatus(bool $deleted, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('DISTINCT c, a')
            ->leftJoin('c.addresses', 'a')->addSelect('a')
            ->andWhere('c.isDeleted = :deleted')
            ->setParameter('deleted', $deleted)
            ->orderBy('c.createdAt', 'DESC');

        if ($q !== null && trim($q) !== '') {
            $qb
                ->andWhere('LOWER(c.email) LIKE :q OR LOWER(c.firstname) LIKE :q OR LOWER(c.lastname) LIKE :q OR LOWER(a.city) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
