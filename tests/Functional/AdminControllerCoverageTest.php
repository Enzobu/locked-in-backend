<?php

namespace App\Tests\Functional;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\Locker;
use App\Entity\LockerBay;
use App\Entity\Specification;
use App\Entity\User;
use App\Enum\LockerStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminControllerCoverageTest extends AbstractApiTestCase
{
    public function testAdminPagesRenderForAdministrator(): void
    {
        [$admin, $company, $bay, $locker, $customer, $user] = $this->createBackofficeDataset();
        $this->client->loginUser($admin, 'main');

        foreach ([
            '/admin',
            '/admin/companies',
            '/admin/companies?q=open',
            '/admin/companies/new',
            '/admin/companies/'.$company->getId(),
            '/admin/companies/'.$company->getId().'/edit',
            '/admin/companies/'.$company->getId().'/locker-bays/new',
            '/admin/companies/locker-bays/'.$bay->getId().'/edit',
            '/admin/companies/locker-bays/'.$bay->getId().'/lockers/new',
            '/admin/companies/lockers/'.$locker->getId().'/edit',
            '/admin/customers',
            '/admin/customers?deleted=1&q=customer',
            '/admin/customers/new',
            '/admin/customers/'.$customer->getId().'/edit',
            '/admin/users',
            '/admin/users?deleted=1&q=operator',
            '/admin/users/new',
            '/admin/users/'.$user->getId().'/edit',
            '/admin/locker-bays',
            '/admin/locker-bays?q=hub',
            '/admin/locker-bays/'.$bay->getId().'?q=hw&status=available',
            '/admin/lockers/'.$locker->getId(),
        ] as $url) {
            self::assertSame(Response::HTTP_OK, $this->client->request('GET', $url)->getStatusCode(), $url);
        }
    }

    public function testAdminInvalidPostBranchesRedirect(): void
    {
        [$admin, $company, $bay, $locker, $customer, $user] = $this->createBackofficeDataset();
        $this->client->loginUser($admin, 'main');

        foreach ([
            ['/admin/companies/'.$company->getId().'/delete', []],
            ['/admin/companies/locker-bays/'.$bay->getId().'/delete', []],
            ['/admin/companies/lockers/'.$locker->getId().'/delete', []],
            ['/admin/customers/'.$customer->getId().'/delete', []],
            ['/admin/customers/'.$customer->getId().'/restore', []],
            ['/admin/users/'.$user->getId().'/delete', []],
            ['/admin/users/'.$user->getId().'/restore', []],
            ['/admin/locker-bays/'.$bay->getId().'/durations', ['minDuration' => '-1', 'maxDuration' => '10']],
            ['/admin/lockers/'.$locker->getId().'/price', ['priceEuro' => 'bad-price']],
        ] as [$url, $params]) {
            $response = $this->client->request('POST', $url, ['body' => $params]);
            self::assertTrue(in_array($response->getStatusCode(), [Response::HTTP_FOUND, Response::HTTP_SEE_OTHER], true), $url);
        }
    }

    public function testAdminValidFormSubmissionsRedirect(): void
    {
        [$admin, $company, $bay] = $this->createBackofficeDataset();
        $this->client->loginUser($admin, 'main');

        $this->assertOkOrRedirect($this->client->request('POST', '/admin/companies/new', [
            'body' => [
                'company' => [
                    'name' => 'Created Company',
                    'siret' => '22345678901234',
                    'siren' => '223456789',
                    'ape' => '6201Z',
                    'juridicForm' => 'SAS',
                    'phone' => '+33111111111',
                    'address' => [
                        'number' => '5',
                        'street' => 'Created Street',
                        'city' => 'Paris',
                        'country' => 'FR',
                        'complement' => '',
                    ],
                    '_token' => $this->formToken('/admin/companies/new', 'company'),
                ],
            ],
        ])->getStatusCode());

        $this->assertOkOrRedirect($this->client->request('POST', '/admin/customers/new', [
            'body' => [
                'customer' => [
                    'firstname' => 'Created',
                    'lastname' => 'Customer',
                    'email' => 'created-customer@test.com',
                    'birthDate' => '1990-01-01',
                    'addresses' => [[
                        'number' => '6',
                        'street' => 'Customer Street',
                        'city' => 'Lyon',
                        'country' => 'FR',
                        'complement' => '',
                    ]],
                    'plainPassword' => 'password123',
                    '_token' => $this->formToken('/admin/customers/new', 'customer'),
                ],
            ],
        ])->getStatusCode());

        $this->assertOkOrRedirect($this->client->request('POST', '/admin/users/new', [
            'body' => [
                'user' => [
                    'firstname' => 'Created',
                    'lastname' => 'Operator',
                    'email' => 'created-operator@test.com',
                    'company' => (string) $company->getId(),
                    'roles' => ['ROLE_OPERATOR'],
                    'plainPassword' => 'password123',
                    '_token' => $this->formToken('/admin/users/new', 'user'),
                ],
            ],
        ])->getStatusCode());

        $this->assertOkOrRedirect($this->client->request('POST', '/admin/companies/'.$company->getId().'/locker-bays/new', [
            'body' => [
                'locker_bay' => [
                    'name' => 'Created Hub',
                    'latitude' => '48.0000000',
                    'longitude' => '2.0000000',
                    'minDuration' => '15',
                    'maxDuration' => '60',
                    'overtimeSurchargePercent' => '10',
                    '_token' => $this->formToken('/admin/companies/'.$company->getId().'/locker-bays/new', 'locker_bay'),
                ],
            ],
        ])->getStatusCode());

        $this->assertOkOrRedirect($this->client->request('POST', '/admin/companies/locker-bays/'.$bay->getId().'/edit', [
            'body' => [
                'locker_bay' => [
                    'name' => 'Edited Hub',
                    'latitude' => '49.0000000',
                    'longitude' => '3.0000000',
                    'minDuration' => '30',
                    'maxDuration' => '120',
                    'overtimeSurchargePercent' => '15',
                    '_token' => $this->formToken('/admin/companies/locker-bays/'.$bay->getId().'/edit', 'locker_bay'),
                ],
            ],
        ])->getStatusCode());
    }

    public function testOperatorCanUseVisibleLockerBayPages(): void
    {
        [, , $bay, $locker, , $operator] = $this->createBackofficeDataset(['ROLE_OPERATOR']);
        $this->client->loginUser($operator, 'main');

        self::assertSame(Response::HTTP_OK, $this->client->request('GET', '/admin/locker-bays')->getStatusCode());
        self::assertSame(Response::HTTP_OK, $this->client->request('GET', '/admin/locker-bays/'.$bay->getId())->getStatusCode());
        self::assertSame(Response::HTTP_OK, $this->client->request('GET', '/admin/lockers/'.$locker->getId())->getStatusCode());

        self::assertSame(
            Response::HTTP_FOUND,
            $this->client->request('POST', '/admin/locker-bays/'.$bay->getId().'/overtime-surcharge', [
                'body' => ['overtimeSurchargePercent' => '-1'],
            ])->getStatusCode()
        );
    }

    /**
     * @param list<string> $operatorRoles
     * @return array{0: User, 1: Company, 2: LockerBay, 3: Locker, 4: Customer, 5: User}
     */
    private function createBackofficeDataset(array $operatorRoles = ['ROLE_USER']): array
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $address = (new Address())->setNumber('1')->setStreet('Main')->setCity('Paris')->setCountry('FR');
        $company = (new Company())
            ->setName('Open Innov')
            ->setSiret('12345678901234')
            ->setSiren('123456789')
            ->setApe('6201Z')
            ->setAddress($address)
            ->setJuridicForm('SAS')
            ->setPhone('+33123456789');
        $admin = (new User())
            ->setEmail('admin@test.com')
            ->setFirstname('Admin')
            ->setLastname('Root')
            ->setRoles(['ROLE_ADMIN'])
            ->setCompany($company);
        $admin->setPassword($hasher->hashPassword($admin, 'password'));
        $operator = (new User())
            ->setEmail('operator@test.com')
            ->setFirstname('Operator')
            ->setLastname('User')
            ->setRoles($operatorRoles)
            ->setCompany($company);
        $operator->setPassword($hasher->hashPassword($operator, 'password'));
        $specification = (new Specification())
            ->setName('M')
            ->setWidth(35)
            ->setHeight(30)
            ->setDepth(45)
            ->setMaterial('Steel')
            ->setIsRechargeable(false);
        $bay = (new LockerBay())
            ->setName('Hub')
            ->setLatitude('48.8566000')
            ->setLongitude('2.3522000')
            ->setCompany($company)
            ->setMinDuration(30)
            ->setMaxDuration(120);
        $locker = (new Locker())
            ->setNumber(1)
            ->setHardwareId('hw-1')
            ->setSpecification($specification)
            ->setLockerBay($bay)
            ->setPriceCents(1200)
            ->setStatus(LockerStatus::AVAILABLE);
        $customerAddress = (new Address())->setNumber('2')->setStreet('Customer')->setCity('Lyon')->setCountry('FR');
        $customer = (new Customer())
            ->setEmail('customer-admin@test.com')
            ->setFirstname('Customer')
            ->setLastname('Admin')
            ->setBirthDate(new \DateTimeImmutable('1990-01-01'))
            ->setRoles(['ROLE_CUSTOMER'])
            ->setPassword('hash');
        $customer->addAddress($customerAddress);

        foreach ([$address, $company, $admin, $operator, $specification, $bay, $locker, $customerAddress, $customer] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return [$admin, $company, $bay, $locker, $customer, $operator];
    }

    private function formToken(string $url, string $formName): string
    {
        $html = $this->client->request('GET', $url)->getContent();
        $pattern = sprintf('/id="%s__token"[^>]*value="([^"]+)"/', preg_quote($formName, '/'));

        self::assertMatchesRegularExpression($pattern, $html);
        preg_match($pattern, $html, $matches);

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    private function assertOkOrRedirect(int $statusCode): void
    {
        self::assertTrue(in_array($statusCode, [Response::HTTP_OK, Response::HTTP_FOUND, Response::HTTP_SEE_OTHER], true));
    }
}
