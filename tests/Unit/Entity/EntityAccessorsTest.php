<?php

namespace App\Tests\Unit\Entity;

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
use PHPUnit\Framework\TestCase;

final class EntityAccessorsTest extends TestCase
{
    public function testUserAccessorsAndLifecycleHelpers(): void
    {
        $company = new Company();
        $user = (new User())
            ->setEmail('  USER@Example.COM ')
            ->setFirstname('Ada')
            ->setLastname('Lovelace')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hash')
            ->setCompany($company);

        self::assertNull($user->getId());
        self::assertSame('user@example.com', $user->getEmail());
        self::assertSame('user@example.com', $user->getUserIdentifier());
        self::assertSame('Ada', $user->getFirstname());
        self::assertSame('Lovelace', $user->getLastname());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertSame('hash', $user->getPassword());
        self::assertSame($company, $user->getCompany());
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getUpdatedAt());

        $user->setIsDeleted(true);
        self::assertTrue($user->isDeleted());
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getDeletedAt());

        $deletedAt = new \DateTimeImmutable('2024-01-01');
        $user->setDeletedAt($deletedAt);
        self::assertSame($deletedAt, $user->getDeletedAt());

        $user->restore();
        self::assertFalse($user->isDeleted());
        self::assertNull($user->getDeletedAt());

        $user->softDelete();
        self::assertTrue($user->isDeleted());
        $user->setIsDeleted(false);
        self::assertNull($user->getDeletedAt());

        $user->onPrePersist();
        $user->onPreUpdate();
        $user->eraseCredentials();
        self::assertArrayHasKey("\0".User::class."\0password", $user->__serialize());
    }

    public function testCompanyAddressCustomerRelations(): void
    {
        $address = (new Address())
            ->setNumber('12')
            ->setStreet('Main Street')
            ->setCity('Paris')
            ->setCountry('France')
            ->setComplement('Apt 3');
        $company = (new Company())
            ->setName('Open Innov')
            ->setSiret('12345678901234')
            ->setSiren('123456789')
            ->setApe('6201Z')
            ->setAddress($address)
            ->setJuridicForm('SAS')
            ->setPhone('+33123456789');
        $user = new User();
        $bay = new LockerBay();

        $company->addUser($user)->addLockerBay($bay);

        self::assertNull($address->getId());
        self::assertSame('12', $address->getNumber());
        self::assertSame('Main Street', $address->getStreet());
        self::assertSame('Paris', $address->getCity());
        self::assertSame('France', $address->getCountry());
        self::assertSame('Apt 3', $address->getComplement());
        self::assertSame('Open Innov', $company->getName());
        self::assertSame('12345678901234', $company->getSiret());
        self::assertSame('123456789', $company->getSiren());
        self::assertSame('6201Z', $company->getApe());
        self::assertSame($address, $company->getAddress());
        self::assertSame('SAS', $company->getJuridicForm());
        self::assertSame('+33123456789', $company->getPhone());
        self::assertSame($company, $user->getCompany());
        self::assertSame($company, $bay->getCompany());
        self::assertCount(1, $company->getUsers());
        self::assertCount(1, $company->getLockerBays());
        self::assertInstanceOf(\DateTimeImmutable::class, $company->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $company->getUpdatedAt());

        $address->addCompany($company);
        self::assertTrue($address->getCompanies()->contains($company));
        $address->removeCompany($company);
        self::assertFalse($address->getCompanies()->contains($company));
        self::assertNull($company->getAddress());

        $company->removeUser($user)->removeLockerBay($bay);
        self::assertCount(0, $company->getUsers());
        self::assertCount(0, $company->getLockerBays());
        $company->onPrePersist();
        $company->onPreUpdate();
    }

    public function testCustomerAddressReservationLifecycleHelpers(): void
    {
        $address = new Address();
        $reservation = new Reservation();
        $createdAt = new \DateTimeImmutable('2024-01-01');
        $updatedAt = new \DateTimeImmutable('2024-01-02');
        $birthDate = new \DateTimeImmutable('1990-01-01');

        $customer = (new Customer())
            ->setEmail(' CUSTOMER@Example.COM ')
            ->setFirstname('Grace')
            ->setLastname('Hopper')
            ->setBirthDate($birthDate)
            ->setRoles(['ROLE_CUSTOMER'])
            ->setPassword('hash')
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setStripeCustomerId('cus_123');

        $customer->addAddress($address)->addReservation($reservation);

        self::assertNull($customer->getId());
        self::assertSame('customer@example.com', $customer->getEmail());
        self::assertSame('customer@example.com', $customer->getUserIdentifier());
        self::assertSame('Grace', $customer->getFirstname());
        self::assertSame('Hopper', $customer->getLastname());
        self::assertSame($birthDate, $customer->getBirthDate());
        self::assertContains('ROLE_CUSTOMER', $customer->getRoles());
        self::assertSame('hash', $customer->getPassword());
        self::assertSame($address, $customer->getPrimaryAddress());
        self::assertSame($customer, $address->getCustomer());
        self::assertSame($customer, $reservation->getCustomer());
        self::assertSame($createdAt, $customer->getCreatedAt());
        self::assertSame($updatedAt, $customer->getUpdatedAt());
        self::assertSame('cus_123', $customer->getStripeCustomerId());

        $customer->setAddress(null);
        self::assertCount(0, $customer->getAddresses());
        $customer->setAddress($address);
        self::assertSame($address, $customer->getPrimaryAddress());
        $customer->removeAddress($address);
        self::assertNull($address->getCustomer());
        $customer->removeReservation($reservation);
        self::assertCount(0, $customer->getReservations());

        $customer->setIsDeleted(true);
        self::assertTrue($customer->isDeleted());
        self::assertInstanceOf(\DateTimeImmutable::class, $customer->getDeletedAt());
        $customer->setDeletedAt($createdAt);
        self::assertSame($createdAt, $customer->getDeletedAt());
        $customer->restore();
        self::assertFalse($customer->isDeleted());
        $customer->softDelete();
        self::assertTrue($customer->isDeleted());
        $customer->eraseCredentials();
        $customer->onPrePersist();
        $customer->onPreUpdate();
    }

    public function testLockerBaySpecificationAndLockerRelations(): void
    {
        $company = new Company();
        $specification = (new Specification())
            ->setName('M')
            ->setWidth(35)
            ->setHeight(30)
            ->setDepth(45)
            ->setMaterial('Steel')
            ->setIsRechargeable(true);
        $bay = (new LockerBay())
            ->setName('Bay')
            ->setLatitude('48.8566000')
            ->setLongitude('2.3522000')
            ->setCompany($company)
            ->setMinDuration(30)
            ->setMaxDuration(120)
            ->setOvertimeSurchargePercent(-10);
        $locker = (new Locker())
            ->setNumber(7)
            ->setHardwareId('HW-7')
            ->setSpecification($specification)
            ->setPriceCents(1200)
            ->setLockerBay($bay)
            ->setStatus(LockerStatus::OCCUPIED)
            ->setDeviceId('device-1')
            ->setLastSeenAt(new \DateTimeImmutable('2024-01-01'));

        $specification->addLocker($locker);
        $bay->addLocker($locker);

        self::assertNull($specification->getId());
        self::assertSame('M', $specification->getName());
        self::assertSame(35, $specification->getWidth());
        self::assertSame(30, $specification->getHeight());
        self::assertSame(45, $specification->getDepth());
        self::assertSame('Steel', $specification->getMaterial());
        self::assertTrue($specification->isRechargeable());
        self::assertSame('Bay', $bay->getName());
        self::assertSame('48.8566000', $bay->getLatitude());
        self::assertSame('2.3522000', $bay->getLongitude());
        self::assertSame($company, $bay->getCompany());
        self::assertSame(30, $bay->getMinDuration());
        self::assertSame(120, $bay->getMaxDuration());
        self::assertSame(0, $bay->getOvertimeSurchargePercent());
        self::assertSame(7, $locker->getNumber());
        self::assertSame('HW-7', $locker->getHardwareId());
        self::assertSame($specification, $locker->getSpecification());
        self::assertSame(1200, $locker->getPriceCents());
        self::assertSame($bay, $locker->getLockerBay());
        self::assertSame(LockerStatus::OCCUPIED, $locker->getStatus());
        self::assertSame('device-1', $locker->getDeviceId());
        self::assertInstanceOf(\DateTimeImmutable::class, $locker->getLastSeenAt());
        self::assertCount(1, $specification->getLockers());
        self::assertCount(1, $bay->getLockers());

        $reservation = new Reservation();
        $locker->addReservation($reservation);
        self::assertSame($locker, $reservation->getLocker());
        $locker->removeReservation($reservation);
        self::assertCount(0, $locker->getReservations());
        self::assertCount(0, $locker->getActions());
        self::assertCount(0, $locker->getEvents());

        $specification->removeLocker($locker);
        self::assertNull($locker->getSpecification());
        $bay->removeLocker($locker);
        self::assertNull($locker->getLockerBay());
        $locker->onPrePersist();
        $locker->onPreUpdate();
        $bay->onPrePersist();
        $bay->onPreUpdate();
    }

    public function testReservationAccessorsAndDerivedDuration(): void
    {
        $startsAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $endsAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $actualEndsAt = new \DateTimeImmutable('2024-01-01 12:15:00');
        $customer = new Customer();
        $locker = new Locker();

        $reservation = (new Reservation())
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setDate($startsAt)
            ->setDuration(90)
            ->setCustomer($customer)
            ->setLocker($locker)
            ->setStatus(ReservationStatus::CONFIRMED)
            ->setPlannedAmountCents(1500)
            ->setCurrency(' EUR ')
            ->setPaymentIntentId('pi_123')
            ->setPaymentStatus(' SUCCEEDED ')
            ->setActualEndsAt($actualEndsAt)
            ->setOvertimeMinutes(-5)
            ->setOvertimeAmountCents(-10)
            ->setOvertimePaymentIntentId('pi_overtime')
            ->setOvertimePaymentStatus(' FAILED ')
            ->setCancelledAt($actualEndsAt)
            ->setRefundId('re_123')
            ->setRefundStatus(' PENDING ');

        self::assertNull($reservation->getId());
        self::assertSame($startsAt, $reservation->getStartsAt());
        self::assertSame($startsAt, $reservation->getDate());
        self::assertSame(90, $reservation->getDuration());
        self::assertSame($customer, $reservation->getCustomer());
        self::assertSame($locker, $reservation->getLocker());
        self::assertSame(ReservationStatus::CONFIRMED, $reservation->getStatus());
        self::assertSame(1500, $reservation->getPlannedAmountCents());
        self::assertSame('eur', $reservation->getCurrency());
        self::assertSame('pi_123', $reservation->getPaymentIntentId());
        self::assertSame('succeeded', $reservation->getPaymentStatus());
        self::assertSame($actualEndsAt, $reservation->getActualEndsAt());
        self::assertSame(0, $reservation->getOvertimeMinutes());
        self::assertSame(0, $reservation->getOvertimeAmountCents());
        self::assertSame('pi_overtime', $reservation->getOvertimePaymentIntentId());
        self::assertSame('failed', $reservation->getOvertimePaymentStatus());
        self::assertSame($actualEndsAt, $reservation->getCancelledAt());
        self::assertSame('re_123', $reservation->getRefundId());
        self::assertSame('pending', $reservation->getRefundStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $reservation->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $reservation->getUpdatedAt());

        self::assertNull((new Reservation())->getDuration());
        $reservation->onPrePersist();
        $reservation->onPreUpdate();
    }

    public function testLockerActionAndEventAccessors(): void
    {
        $locker = new Locker();
        $reservation = new Reservation();
        $user = new User();
        $customer = new Customer();
        $time = new \DateTimeImmutable('2024-01-01');

        $action = (new LockerAction())
            ->setLocker($locker)
            ->setType('open')
            ->setStatus(LockerActionStatus::FAILED)
            ->setReservation($reservation)
            ->setRequestedByUser($user)
            ->setRequestedByCustomer($customer)
            ->setRequestedAt($time)
            ->setProcessedAt($time)
            ->setErrorMessage('timeout');

        self::assertNull($action->getId());
        self::assertSame($locker, $action->getLocker());
        self::assertSame('open', $action->getType());
        self::assertSame(LockerActionStatus::FAILED, $action->getStatus());
        self::assertSame($reservation, $action->getReservation());
        self::assertSame($user, $action->getRequestedByUser());
        self::assertSame($customer, $action->getRequestedByCustomer());
        self::assertSame($time, $action->getRequestedAt());
        self::assertSame($time, $action->getProcessedAt());
        self::assertSame('timeout', $action->getErrorMessage());

        $event = (new LockerEvent())
            ->setLocker($locker)
            ->setReservation($reservation)
            ->setType('door_opened')
            ->setPayload(['ok' => true])
            ->setOccurredAt($time);

        self::assertNull($event->getId());
        self::assertSame($locker, $event->getLocker());
        self::assertSame($reservation, $event->getReservation());
        self::assertSame('door_opened', $event->getType());
        self::assertSame(['ok' => true], $event->getPayload());
        self::assertSame($time, $event->getOccurredAt());
    }
}
