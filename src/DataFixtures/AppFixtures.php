<?php

namespace App\DataFixtures;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\Locker;
use App\Entity\LockerAction;
use App\Entity\LockerBay;
use App\Entity\LockerEvent;
use App\Entity\Reservation;
use App\Entity\Specification;
use App\Entity\User;
use App\Enum\LockerActionStatus;
use App\Enum\LockerStatus;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const CITIES = [
        ['name' => 'Paris', 'lat' => 48.8566130, 'lng' => 2.3522220],
        ['name' => 'Marseille', 'lat' => 43.2964820, 'lng' => 5.3697800],
        ['name' => 'Lyon', 'lat' => 45.7640430, 'lng' => 4.8356590],
        ['name' => 'Toulouse', 'lat' => 43.6046520, 'lng' => 1.4442090],
        ['name' => 'Nice', 'lat' => 43.7101730, 'lng' => 7.2619530],
        ['name' => 'Nantes', 'lat' => 47.2183710, 'lng' => -1.5536210],
        ['name' => 'Montpellier', 'lat' => 43.6107690, 'lng' => 3.8767160],
        ['name' => 'Strasbourg', 'lat' => 48.5734050, 'lng' => 7.7521110],
        ['name' => 'Bordeaux', 'lat' => 44.8377890, 'lng' => -0.5791800],
        ['name' => 'Lille', 'lat' => 50.6292500, 'lng' => 3.0572560],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $fakerFactoryClass = 'Faker\\Factory';
        if (!class_exists($fakerFactoryClass)) {
            throw new \RuntimeException('fakerphp/faker is required. Run: composer require --dev fakerphp/faker');
        }

        $faker = $fakerFactoryClass::create('fr_FR');
        $faker->seed(20260303);

        $specificationIds = $this->createSpecifications($manager);
        [$companyIds, $customerIds] = $this->createCompaniesUsersCustomers($manager, $faker);

        $this->createParksAndReservations($manager, $faker, $companyIds, $customerIds, $specificationIds);
    }

    /**
     * @return int[]
     */
    private function createSpecifications(ObjectManager $manager): array
    {
        $specs = [
            ['name' => 'S - Compact', 'w' => 25, 'h' => 20, 'd' => 35, 'material' => 'Steel', 'rechargeable' => false],
            ['name' => 'M - Standard', 'w' => 35, 'h' => 30, 'd' => 45, 'material' => 'Steel', 'rechargeable' => false],
            ['name' => 'M+ - Recharge', 'w' => 40, 'h' => 35, 'd' => 50, 'material' => 'Steel', 'rechargeable' => true],
            ['name' => 'L - Cargo', 'w' => 50, 'h' => 45, 'd' => 60, 'material' => 'Aluminum', 'rechargeable' => true],
            ['name' => 'XL - Premium', 'w' => 60, 'h' => 60, 'd' => 70, 'material' => 'Composite', 'rechargeable' => true],
        ];

        $ids = [];
        foreach ($specs as $spec) {
            $entity = (new Specification())
                ->setName($spec['name'])
                ->setWidth($spec['w'])
                ->setHeight($spec['h'])
                ->setDepth($spec['d'])
                ->setMaterial($spec['material'])
                ->setIsRechargeable($spec['rechargeable']);

            $manager->persist($entity);
            $ids[] = $entity;
        }

        $manager->flush();

        return array_map(static fn (Specification $s): int => (int) $s->getId(), $ids);
    }

    /**
     * @return array{0: int[], 1: int[]}
     */
    private function createCompaniesUsersCustomers(ObjectManager $manager, object $faker): array
    {
        $companies = [];
        $customers = [];

        for ($i = 1; $i <= 20; ++$i) {
            $city = self::CITIES[($i - 1) % count(self::CITIES)]['name'];

            $address = (new Address())
                ->setNumber((string) $faker->numberBetween(1, 220))
                ->setStreet($faker->streetName())
                ->setCity($city)
                ->setCountry('France')
                ->setComplement($faker->boolean(30) ? ('Batiment '.$faker->randomLetter().$faker->numberBetween(1, 9)) : null);

            $company = (new Company())
                ->setName(sprintf('Societe%02d', $i))
                ->setSiret(sprintf('%014d', 10000000000000 + $i))
                ->setSiren(sprintf('%09d', 100000000 + $i))
                ->setApe('6201Z')
                ->setJuridicForm($faker->randomElement(['SAS', 'SARL']))
                ->setPhone('+33'.sprintf('%09d', $faker->numberBetween(100000000, 999999999)))
                ->setAddress($address);

            $user = (new User())
                ->setEmail(sprintf('admin@societe%02d.com', $i))
                ->setFirstname('Admin')
                ->setLastname(sprintf('Societe%02d', $i))
                ->setRoles(['ROLE_USER'])
                ->setCompany($company);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'admin1234'));

            $manager->persist($address);
            $manager->persist($company);
            $manager->persist($user);

            $companies[] = $company;
        }

        $superAdmin = (new User())
            ->setEmail('superadmin@openinnov.com')
            ->setFirstname('Super')
            ->setLastname('Admin')
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
            ->setCompany($companies[0]);
        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, 'superadmin1234'));
        $manager->persist($superAdmin);

        for ($i = 1; $i <= 10; ++$i) {
            $city = self::CITIES[array_rand(self::CITIES)]['name'];

            $customerAddress = (new Address())
                ->setNumber((string) $faker->numberBetween(1, 220))
                ->setStreet($faker->streetName())
                ->setCity($city)
                ->setCountry('France');

            $customer = (new Customer())
                ->setEmail(sprintf('customer%d@openinnov.com', $i))
                ->setFirstname($faker->firstName())
                ->setLastname($faker->lastName())
                ->setBirthDate(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-55 years', '-20 years')))
                ->setRoles(['ROLE_CUSTOMER']);

            $customer->addAddress($customerAddress);
            $customer->setPassword($this->passwordHasher->hashPassword($customer, 'customer1234'));

            $manager->persist($customerAddress);
            $manager->persist($customer);

            $customers[] = $customer;
        }

        $manager->flush();

        return [
            array_map(static fn (Company $company): int => (int) $company->getId(), $companies),
            array_map(static fn (Customer $customer): int => (int) $customer->getId(), $customers),
        ];
    }

    /**
     * @param int[] $companyIds
     * @param int[] $customerIds
     * @param int[] $specificationIds
     */
    private function createParksAndReservations(ObjectManager $manager, object $faker, array $companyIds, array $customerIds, array $specificationIds): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            throw new \RuntimeException('EntityManagerInterface is required to load fixtures.');
        }

        $entityManager = $manager;
        $now = new \DateTimeImmutable();

        foreach ($companyIds as $index => $companyId) {
            $city = self::CITIES[$index % count(self::CITIES)];

            $bayCount = $faker->numberBetween(3, 6);
            for ($bayIndex = 1; $bayIndex <= $bayCount; ++$bayIndex) {
                /** @var Company $company */
                $company = $entityManager->getReference(Company::class, $companyId);

                $lockerBay = (new LockerBay())
                    ->setName(sprintf('%s Hub %d', $city['name'], $bayIndex))
                    ->setCompany($company)
                    ->setLatitude(number_format($city['lat'] + ($faker->numberBetween(-800, 800) / 100000), 7, '.', ''))
                    ->setLongitude(number_format($city['lng'] + ($faker->numberBetween(-800, 800) / 100000), 7, '.', ''))
                    ->setMinDuration($faker->randomElement([15, 30, 60]))
                    ->setMaxDuration($faker->randomElement([720, 1440, 2880]));

                $entityManager->persist($lockerBay);
                $entityManager->flush();

                $lockerBayId = (int) $lockerBay->getId();

                $lockerCount = $faker->numberBetween(10, 30);
                for ($lockerNumber = 1; $lockerNumber <= $lockerCount; ++$lockerNumber) {
                    $status = $this->randomLockerStatus($faker);
                    $specificationId = $specificationIds[array_rand($specificationIds)];
                    /** @var LockerBay $lockerBayRef */
                    $lockerBayRef = $entityManager->getReference(LockerBay::class, $lockerBayId);
                    /** @var Specification $specification */
                    $specification = $entityManager->getReference(Specification::class, $specificationId);

                    $locker = (new Locker())
                        ->setNumber($lockerNumber)
                        ->setHardwareId(sprintf('LOCK-C%02d-B%02d-L%03d', $index + 1, $bayIndex, $lockerNumber))
                        ->setSpecification($specification)
                        ->setPriceCents($faker->numberBetween(250, 2000))
                        ->setLockerBay($lockerBayRef)
                        ->setStatus($status)
                        ->setLastSeenAt($this->lastSeenForStatus($status, $now, $faker));

                    $entityManager->persist($locker);

                    $reservationCount = $faker->numberBetween(1, 20);
                    for ($r = 0; $r < $reservationCount; ++$r) {
                        $customerId = $customerIds[array_rand($customerIds)];
                        /** @var Customer $customer */
                        $customer = $entityManager->getReference(Customer::class, $customerId);

                        [$startsAt, $endsAt, $reservationStatus] = $this->randomReservationWindow($now, $faker);

                        $reservation = (new Reservation())
                            ->setCustomer($customer)
                            ->setLocker($locker)
                            ->setStartsAt($startsAt)
                            ->setEndsAt($endsAt)
                            ->setStatus($reservationStatus);

                        $entityManager->persist($reservation);

                        if ($faker->boolean(18)) {
                            $event = (new LockerEvent())
                                ->setLocker($locker)
                                ->setReservation($reservation)
                                ->setType($reservationStatus === ReservationStatus::ACTIVE ? 'door_opened' : 'reservation_synced')
                                ->setOccurredAt($startsAt)
                                ->setPayload(['status' => $reservationStatus->value]);
                            $entityManager->persist($event);
                        }

                        if ($faker->boolean(15)) {
                            $actionStatus = $faker->randomElement([
                                LockerActionStatus::SUCCESS,
                                LockerActionStatus::SENT,
                                LockerActionStatus::FAILED,
                            ]);

                            $action = (new LockerAction())
                                ->setLocker($locker)
                                ->setReservation($reservation)
                                ->setType($faker->randomElement(['open', 'close', 'unlock_override']))
                                ->setStatus($actionStatus)
                                ->setRequestedByCustomer($customer)
                                ->setProcessedAt($startsAt->modify('+1 minute'));

                            if ($actionStatus === LockerActionStatus::FAILED) {
                                $action->setErrorMessage('Temporary communication timeout.');
                            }

                            $entityManager->persist($action);
                        }
                    }
                }

                $entityManager->flush();
                $entityManager->clear();
            }
        }

        $entityManager->flush();
    }

    private function randomLockerStatus(object $faker): LockerStatus
    {
        $roll = $faker->numberBetween(1, 100);

        return match (true) {
            $roll <= 10 => LockerStatus::OFFLINE,
            $roll <= 18 => LockerStatus::OUT_OF_ORDER,
            $roll <= 45 => LockerStatus::OCCUPIED,
            $roll <= 62 => LockerStatus::RESERVED,
            default => LockerStatus::AVAILABLE,
        };
    }

    private function lastSeenForStatus(LockerStatus $status, \DateTimeImmutable $now, object $faker): \DateTimeImmutable
    {
        return match ($status) {
            LockerStatus::OFFLINE => $now->modify('-'.$faker->numberBetween(4, 48).' hours'),
            LockerStatus::OUT_OF_ORDER => $now->modify('-'.$faker->numberBetween(2, 12).' hours'),
            default => $now->modify('-'.$faker->numberBetween(1, 120).' minutes'),
        };
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: ReservationStatus}
     */
    private function randomReservationWindow(\DateTimeImmutable $now, object $faker): array
    {
        $bucketRoll = $faker->numberBetween(1, 100);

        if ($bucketRoll <= 60) {
            $startsAt = $now->modify('-'.$faker->numberBetween(2, 180).' days -'.$faker->numberBetween(0, 20).' hours');
            $endsAt = $startsAt->modify('+'.$faker->numberBetween(30, 360).' minutes');
            $status = $faker->randomElement([
                ReservationStatus::COMPLETED,
                ReservationStatus::COMPLETED,
                ReservationStatus::CANCELLED,
                ReservationStatus::EXPIRED,
            ]);

            return [$startsAt, $endsAt, $status];
        }

        if ($bucketRoll <= 90) {
            $startsAt = $now->modify('+'.$faker->numberBetween(1, 120).' days +'.$faker->numberBetween(0, 20).' hours');
            $endsAt = $startsAt->modify('+'.$faker->numberBetween(30, 300).' minutes');
            $status = $faker->boolean(75) ? ReservationStatus::CONFIRMED : ReservationStatus::PENDING;

            return [$startsAt, $endsAt, $status];
        }

        $startsAt = $now->modify('-'.$faker->numberBetween(5, 180).' minutes');
        $endsAt = $now->modify('+'.$faker->numberBetween(10, 240).' minutes');

        return [$startsAt, $endsAt, ReservationStatus::ACTIVE];
    }
}
