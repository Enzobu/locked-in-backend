<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return User[]
     */
    public function searchByDeletionStatus(bool $deleted, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.company', 'c')->addSelect('c')
            ->andWhere('u.isDeleted = :deleted')
            ->setParameter('deleted', $deleted)
            ->orderBy('u.createdAt', 'DESC');

        if ($q !== null && trim($q) !== '') {
            $qb
                ->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.firstname) LIKE :q OR LOWER(u.lastname) LIKE :q OR LOWER(c.name) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
