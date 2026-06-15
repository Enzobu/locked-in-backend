<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\Locker;
use App\Entity\LockerBay;
use App\Entity\Specification;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixturesTest extends AbstractApiTestCase
{
    public function testLightFixturesLoadCoherentDataset(): void
    {
        $_SERVER['FIXTURES_MODE'] = 'light';
        $_ENV['FIXTURES_MODE'] = 'light';
        putenv('FIXTURES_MODE=light');

        $fixtures = new AppFixtures(static::getContainer()->get(UserPasswordHasherInterface::class));
        $fixtures->load($this->em);
        $this->em->clear();

        self::assertSame(5, $this->countRows(Specification::class));
        self::assertSame(2, $this->countRows(Company::class));
        self::assertSame(3, $this->countRows(User::class));
        self::assertSame(2, $this->countRows(Customer::class));
        self::assertGreaterThanOrEqual(4, $this->countRows(LockerBay::class));
        self::assertGreaterThanOrEqual(4, $this->countRows(Locker::class));
    }

    /**
     * @param class-string $className
     */
    private function countRows(string $className): int
    {
        return (int) $this->em->getRepository($className)->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
