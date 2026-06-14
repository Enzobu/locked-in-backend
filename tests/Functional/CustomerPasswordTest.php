<?php

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/customers/me/password: validates the current password and strength,
 * then the new password becomes the one that works for login.
 */
final class CustomerPasswordTest extends AbstractApiTestCase
{
    private const EMAIL = 'pwd@test.com';

    private function changePassword(string $token, array $json): int
    {
        return $this->authedRequest('POST', '/api/customers/me/password', $token, $json)->getStatusCode();
    }

    private function loginStatus(string $password): int
    {
        return $this->client->request('POST', '/api/login', [
            'json' => ['email' => self::EMAIL, 'password' => $password],
        ])->getStatusCode();
    }

    public function testWrongCurrentPasswordIsRejected(): void
    {
        $token = $this->tokenFor($this->createCustomer(self::EMAIL, 'password'));
        self::assertSame(Response::HTTP_FORBIDDEN, $this->changePassword($token, ['currentPassword' => 'nope', 'newPassword' => 'newStrongPass1']));
    }

    public function testTooShortNewPasswordIsRejected(): void
    {
        $token = $this->tokenFor($this->createCustomer(self::EMAIL, 'password'));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->changePassword($token, ['currentPassword' => 'password', 'newPassword' => 'short']));
    }

    public function testSameAsCurrentIsRejected(): void
    {
        $token = $this->tokenFor($this->createCustomer(self::EMAIL, 'password'));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->changePassword($token, ['currentPassword' => 'password', 'newPassword' => 'password']));
    }

    public function testSuccessfulChangeUpdatesLoginCredentials(): void
    {
        $token = $this->tokenFor($this->createCustomer(self::EMAIL, 'password'));

        self::assertSame(Response::HTTP_NO_CONTENT, $this->changePassword($token, ['currentPassword' => 'password', 'newPassword' => 'newStrongPass1']));
        self::assertSame(Response::HTTP_OK, $this->loginStatus('newStrongPass1'));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->loginStatus('password'));
    }
}
