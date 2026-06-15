<?php

namespace App\Tests\Functional;

use App\Entity\Address;
use Symfony\Component\HttpFoundation\Response;

final class CustomerProfileTest extends AbstractApiTestCase
{
    public function testCustomerCanReadAndUpdateProfile(): void
    {
        $customer = $this->createCustomer('profile@test.com', 'password');
        $address = (new Address())->setNumber('3')->setStreet('Rue Test')->setCity('Paris')->setCountry('FR')->setComplement('B');
        $this->em->persist($address);
        $this->em->flush();
        $token = $this->tokenFor($customer);

        $me = $this->authedRequest('GET', '/api/customers/me', $token)->toArray();
        self::assertSame('profile@test.com', $me['email']);
        self::assertSame([], $me['addresses']);

        $response = $this->authedRequest('PATCH', '/api/customers', $token, [
            'firstname' => 'Updated',
            'lastname' => 'Profile',
            'email' => ' UPDATED@Example.COM ',
            'birthDate' => '1991-02-03',
            'address' => '/api/addresses/'.$address->getId(),
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $response->toArray();
        self::assertSame('updated@example.com', $body['email']);
        self::assertSame('Updated', $body['firstname']);
        self::assertCount(1, $body['addresses']);
    }

    public function testProfileUpdateRejectsInvalidPayloads(): void
    {
        $token = $this->tokenFor($this->createCustomer('profile-errors@test.com', 'password'));

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->authedRequest('PATCH', '/api/customers', $token, ['birthDate' => 'not-a-date'])->getStatusCode()
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->authedRequest('PATCH', '/api/customers', $token, ['address' => 'bad-reference'])->getStatusCode()
        );
    }
}
