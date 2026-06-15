<?php

namespace App\Tests\Functional;

use App\Entity\Address;
use App\Repository\CustomerRepository;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationTest extends AbstractApiTestCase
{
    public function testCustomerCanRegisterWithoutAddress(): void
    {
        $response = $this->client->request('POST', '/api/register', [
            'json' => [
                'email' => 'new.customer@test.com',
                'password' => 'password123',
                'firstname' => 'New',
                'lastname' => 'Customer',
                'birthDate' => '1990-01-01',
            ],
        ]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $body = $response->toArray();
        self::assertSame('new.customer@test.com', $body['email']);
        self::assertArrayHasKey('token', $body);

        $this->em->clear();
        $customer = static::getContainer()->get(CustomerRepository::class)->findOneBy(['email' => 'new.customer@test.com']);

        self::assertNotNull($customer);
        self::assertCount(0, $customer->getAddresses());
    }

    public function testCustomerCanRegisterWithAddressReference(): void
    {
        $address = (new Address())->setNumber('1')->setStreet('Main')->setCity('Paris')->setCountry('FR');
        $this->em->persist($address);
        $this->em->flush();

        $response = $this->client->request('POST', '/api/register', [
            'json' => [
                'email' => 'with.address@test.com',
                'password' => 'password123',
                'firstname' => 'With',
                'lastname' => 'Address',
                'birthDate' => '1990-01-01',
                'address' => '/api/addresses/'.$address->getId(),
            ],
        ]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testRegistrationValidationErrors(): void
    {
        $this->createCustomer('existing@test.com', 'password123');

        $cases = [
            [['password' => 'password123'], Response::HTTP_BAD_REQUEST],
            [['email' => 'bad-email', 'password' => 'password123', 'firstname' => 'A', 'lastname' => 'B', 'birthDate' => '1990-01-01'], Response::HTTP_BAD_REQUEST],
            [['email' => 'existing@test.com', 'password' => 'password123', 'firstname' => 'A', 'lastname' => 'B', 'birthDate' => '1990-01-01'], Response::HTTP_CONFLICT],
            [['email' => 'short@test.com', 'password' => 'short', 'firstname' => 'A', 'lastname' => 'B', 'birthDate' => '1990-01-01'], Response::HTTP_BAD_REQUEST],
            [['email' => 'date@test.com', 'password' => 'password123', 'firstname' => 'A', 'lastname' => 'B', 'birthDate' => 'bad-date'], Response::HTTP_BAD_REQUEST],
            [['email' => 'address-ref@test.com', 'password' => 'password123', 'firstname' => 'A', 'lastname' => 'B', 'birthDate' => '1990-01-01', 'address' => 'bad-reference'], Response::HTTP_BAD_REQUEST],
            [['email' => 'address-missing@test.com', 'password' => 'password123', 'firstname' => 'A', 'lastname' => 'B', 'birthDate' => '1990-01-01', 'address' => 999999], Response::HTTP_BAD_REQUEST],
        ];

        foreach ($cases as [$json, $expectedStatus]) {
            static::getContainer()->get('cache.rate_limiter')->clear();
            self::assertSame($expectedStatus, $this->client->request('POST', '/api/register', ['json' => $json])->getStatusCode());
        }
    }
}
