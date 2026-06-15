<?php

namespace App\Tests\Functional;

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
}
