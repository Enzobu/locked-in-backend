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
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $hqAddress = (new Address())
            ->setNumber('12')
            ->setStreet('Rue de la Republique')
            ->setCity('Lyon')
            ->setCountry('France')
            ->setComplement('Batiment A');

        $secondaryAddress = (new Address())
            ->setNumber('5B')
            ->setStreet('Avenue des Alpes')
            ->setCity('Grenoble')
            ->setCountry('France');

        $manager->persist($hqAddress);
        $manager->persist($secondaryAddress);

        $company = (new Company())
            ->setName('Open Innov')
            ->setSiret('12345678901234')
            ->setSiren('123456789')
            ->setApe('6201Z')
            ->setJuridicForm('SAS')
            ->setPhone('+33472000000')
            ->setAddress($hqAddress);

        $manager->persist($company);

        $superAdmin = (new User())
            ->setEmail('gmao@gmail.com')
            ->setFirstname('Alice')
            ->setLastname('Martin')
            ->setRoles(['ROLE_ADMIN', 'ROLE_OPERATOR'])
            ->setCompany($company);
        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, 'vR2gP5kykK'));

        $operator = (new User())
            ->setEmail('operator@openinnov.local')
            ->setFirstname('Nicolas')
            ->setLastname('Durand')
            ->setRoles(['ROLE_OPERATOR'])
            ->setCompany($company);
        $operator->setPassword($this->passwordHasher->hashPassword($operator, 'operator1234'));

        $manager->persist($superAdmin);
        $manager->persist($operator);

        $smallSpec = (new Specification())
            ->setName('S - Standard')
            ->setWidth(30)
            ->setHeight(20)
            ->setDepth(40)
            ->setMaterial('Steel')
            ->setIsRechargeable(false);

        $mediumSpec = (new Specification())
            ->setName('M - Charge')
            ->setWidth(40)
            ->setHeight(30)
            ->setDepth(50)
            ->setMaterial('Steel')
            ->setIsRechargeable(true);

        $largeSpec = (new Specification())
            ->setName('L - XL')
            ->setWidth(50)
            ->setHeight(45)
            ->setDepth(60)
            ->setMaterial('Aluminum')
            ->setIsRechargeable(true);

        $manager->persist($smallSpec);
        $manager->persist($mediumSpec);
        $manager->persist($largeSpec);

        $lockerBayCenter = (new LockerBay())
            ->setName('Lyon Centre')
            ->setCompany($company)
            ->setLatitude('45.7640430')
            ->setLongitude('4.8356590')
            ->setMinDuration(30)
            ->setMaxDuration(24 * 60);

        $lockerBayStation = (new LockerBay())
            ->setName('Lyon Part-Dieu')
            ->setCompany($company)
            ->setLatitude('45.7600000')
            ->setLongitude('4.8600000')
            ->setMinDuration(30)
            ->setMaxDuration(12 * 60);

        $manager->persist($lockerBayCenter);
        $manager->persist($lockerBayStation);

        $locker1 = (new Locker())
            ->setNumber(1)
            ->setSpecification($smallSpec)
            ->setLockerBay($lockerBayCenter)
            ->setPriceCents(300)
            ->setHardwareId('LOCK-LYON-C-001')
            ->setStatus(LockerStatus::AVAILABLE)
            ->setLastSeenAt(new \DateTimeImmutable('-2 minutes'));

        $locker2 = (new Locker())
            ->setNumber(2)
            ->setSpecification($mediumSpec)
            ->setLockerBay($lockerBayCenter)
            ->setPriceCents(450)
            ->setHardwareId('LOCK-LYON-C-002')
            ->setStatus(LockerStatus::RESERVED)
            ->setLastSeenAt(new \DateTimeImmutable('-1 minutes'));

        $locker3 = (new Locker())
            ->setNumber(1)
            ->setSpecification($largeSpec)
            ->setLockerBay($lockerBayStation)
            ->setPriceCents(650)
            ->setHardwareId('LOCK-LYON-PD-001')
            ->setStatus(LockerStatus::OCCUPIED)
            ->setLastSeenAt(new \DateTimeImmutable('-5 minutes'));

        $locker4 = (new Locker())
            ->setNumber(2)
            ->setSpecification($smallSpec)
            ->setLockerBay($lockerBayStation)
            ->setPriceCents(250)
            ->setHardwareId('LOCK-LYON-PD-002')
            ->setStatus(LockerStatus::OFFLINE)
            ->setLastSeenAt(new \DateTimeImmutable('-3 hours'));

        $manager->persist($locker1);
        $manager->persist($locker2);
        $manager->persist($locker3);
        $manager->persist($locker4);

        $customerA = (new Customer())
            ->setEmail('lea.dupont@example.com')
            ->setFirstname('Lea')
            ->setLastname('Dupont')
            ->setBirthDate(new \DateTimeImmutable('1995-03-14'))
            ->setRoles(['ROLE_CUSTOMER']);
        $customerA->addAddress($secondaryAddress);
        $customerA->setPassword($this->passwordHasher->hashPassword($customerA, 'customer1234'));

        $customerB = (new Customer())
            ->setEmail('yanis.bernard@example.com')
            ->setFirstname('Yanis')
            ->setLastname('Bernard')
            ->setBirthDate(new \DateTimeImmutable('1991-08-28'))
            ->setRoles(['ROLE_CUSTOMER']);
        $customerB->addAddress($hqAddress);
        $customerB->setPassword($this->passwordHasher->hashPassword($customerB, 'customer1234'));

        $manager->persist($customerA);
        $manager->persist($customerB);

        $activeReservation = (new Reservation())
            ->setCustomer($customerA)
            ->setLocker($locker3)
            ->setStartsAt(new \DateTimeImmutable('-30 minutes'))
            ->setEndsAt(new \DateTimeImmutable('+30 minutes'))
            ->setStatus(ReservationStatus::ACTIVE);

        $upcomingReservation = (new Reservation())
            ->setCustomer($customerB)
            ->setLocker($locker2)
            ->setStartsAt(new \DateTimeImmutable('+45 minutes'))
            ->setEndsAt(new \DateTimeImmutable('+2 hours'))
            ->setStatus(ReservationStatus::CONFIRMED);

        $completedReservation = (new Reservation())
            ->setCustomer($customerA)
            ->setLocker($locker1)
            ->setStartsAt(new \DateTimeImmutable('-2 days'))
            ->setEndsAt(new \DateTimeImmutable('-2 days +2 hours'))
            ->setStatus(ReservationStatus::COMPLETED);

        $manager->persist($activeReservation);
        $manager->persist($upcomingReservation);
        $manager->persist($completedReservation);

        $openAction = (new LockerAction())
            ->setLocker($locker3)
            ->setReservation($activeReservation)
            ->setType('open')
            ->setStatus(LockerActionStatus::SUCCESS)
            ->setRequestedByCustomer($customerA)
            ->setProcessedAt(new \DateTimeImmutable('-25 minutes'));

        $adminAction = (new LockerAction())
            ->setLocker($locker2)
            ->setReservation($upcomingReservation)
            ->setType('unlock_override')
            ->setStatus(LockerActionStatus::SENT)
            ->setRequestedByUser($operator);

        $failedAction = (new LockerAction())
            ->setLocker($locker4)
            ->setType('ping')
            ->setStatus(LockerActionStatus::FAILED)
            ->setRequestedByUser($superAdmin)
            ->setProcessedAt(new \DateTimeImmutable('-2 hours'))
            ->setErrorMessage('No response from locker controller.');

        $manager->persist($openAction);
        $manager->persist($adminAction);
        $manager->persist($failedAction);

        $manager->persist(
            (new LockerEvent())
                ->setLocker($locker3)
                ->setReservation($activeReservation)
                ->setType('door_opened')
                ->setOccurredAt(new \DateTimeImmutable('-25 minutes'))
                ->setPayload(['source' => 'mobile_app'])
        );

        $manager->persist(
            (new LockerEvent())
                ->setLocker($locker3)
                ->setReservation($activeReservation)
                ->setType('door_closed')
                ->setOccurredAt(new \DateTimeImmutable('-24 minutes'))
                ->setPayload(['source' => 'sensor'])
        );

        $manager->persist(
            (new LockerEvent())
                ->setLocker($locker4)
                ->setType('heartbeat_timeout')
                ->setOccurredAt(new \DateTimeImmutable('-2 hours'))
                ->setPayload(['last_seen_minutes_ago' => 180])
        );

        $manager->flush();
    }
}
