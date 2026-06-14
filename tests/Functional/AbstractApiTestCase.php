<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Address;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\Locker;
use App\Entity\LockerBay;
use App\Entity\Specification;
use App\Enum\LockerStatus;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for HTTP functional tests: boots the API Platform test client,
 * resets the test database before each test and offers small data factories.
 */
abstract class AbstractApiTestCase extends ApiTestCase
{
    protected Client $client;
    protected EntityManagerInterface $em;

    private int $sequence = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Keep one kernel/connection for the whole test so writes performed by API
        // requests are visible to the test's EntityManager (after clear()).
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase();
    }

    private function resetDatabase(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($connection->createSchemaManager()->listTableNames() as $table) {
            if ($table === 'doctrine_migration_versions') {
                continue;
            }
            $connection->executeStatement('TRUNCATE TABLE '.$connection->quoteIdentifier($table));
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    protected function createCustomer(string $email = 'customer@test.com', string $password = 'password'): Customer
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $customer = (new Customer())
            ->setEmail($email)
            ->setFirstname('Test')
            ->setLastname('Customer')
            ->setBirthDate(new \DateTimeImmutable('1990-01-01'));
        $customer->setPassword($hasher->hashPassword($customer, $password));

        $this->em->persist($customer);
        $this->em->flush();

        return $customer;
    }

    protected function createLocker(
        LockerStatus $status = LockerStatus::AVAILABLE,
        int $minDuration = 60,
        int $maxDuration = 720,
        int $priceCents = 1200,
        int $surchargePercent = 0,
    ): Locker {
        ++$this->sequence;

        $address = (new Address())->setNumber('1')->setStreet('Main St')->setCity('Paris')->setCountry('FR');

        $company = (new Company())
            ->setName('Company '.$this->sequence)
            ->setSiret(str_pad((string) $this->sequence, 14, '0', STR_PAD_LEFT))
            ->setSiren(str_pad((string) $this->sequence, 9, '0', STR_PAD_LEFT))
            ->setApe('6201Z')
            ->setJuridicForm('SAS')
            ->setPhone('0102030405')
            ->setAddress($address);

        $bay = (new LockerBay())
            ->setName('Bay '.$this->sequence)
            ->setLatitude('48.8566000')
            ->setLongitude('2.3522000')
            ->setCompany($company)
            ->setMinDuration($minDuration)
            ->setMaxDuration($maxDuration)
            ->setOvertimeSurchargePercent($surchargePercent);

        $specification = (new Specification())
            ->setName('Spec '.$this->sequence)
            ->setWidth(35)->setHeight(30)->setDepth(45)
            ->setMaterial('Steel')
            ->setIsRechargeable(false);

        $locker = (new Locker())
            ->setNumber($this->sequence)
            ->setPriceCents($priceCents)
            ->setSpecification($specification)
            ->setLockerBay($bay)
            ->setStatus($status);

        $this->em->persist($address);
        $this->em->persist($company);
        $this->em->persist($bay);
        $this->em->persist($specification);
        $this->em->persist($locker);
        $this->em->flush();

        return $locker;
    }

    protected function tokenFor(Customer $customer): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($customer);
    }

    /**
     * @param array<string, mixed> $json
     */
    protected function authedRequest(string $method, string $url, string $token, array $json = [], ?string $contentType = null): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $options = ['headers' => ['Authorization' => 'Bearer '.$token]];
        if ($json !== []) {
            $options['json'] = $json;
        }
        if ($contentType !== null) {
            $options['headers']['Content-Type'] = $contentType;
        }

        return $this->client->request($method, $url, $options);
    }

    protected function future(int $daysFromNow, int $hour = 10): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today 00:00'))->modify(sprintf('+%d days', $daysFromNow))->setTime($hour, 0);
    }

    protected function iso(\DateTimeImmutable $dt): string
    {
        return $dt->format(\DateTimeInterface::ATOM);
    }
}
