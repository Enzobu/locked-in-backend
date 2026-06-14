<?php

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

/**
 * Auth endpoints are rate limited to slow down brute-force / abuse.
 * The test env uses an in-memory limiter cache, reset on each kernel boot.
 */
final class RateLimitTest extends AbstractApiTestCase
{
    public function testRegistrationIsRateLimited(): void
    {
        // The limiter (5 / 15 min) is consumed before validation, so invalid
        // bodies still count. The 6th attempt from the same IP is blocked.
        $statuses = [];
        for ($i = 1; $i <= 6; ++$i) {
            $statuses[$i] = $this->client->request('POST', '/api/register', ['json' => ['noop' => $i]])->getStatusCode();
        }

        self::assertNotSame(Response::HTTP_TOO_MANY_REQUESTS, $statuses[1]);
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $statuses[6]);
    }

    public function testLoginIsRateLimited(): void
    {
        $this->createCustomer('throttle@test.com', 'password');

        $statuses = [];
        for ($i = 1; $i <= 6; ++$i) {
            $statuses[$i] = $this->client->request('POST', '/api/login', [
                'json' => ['email' => 'throttle@test.com', 'password' => 'wrong-password'],
            ])->getStatusCode();
        }

        self::assertSame(Response::HTTP_UNAUTHORIZED, $statuses[1]);
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $statuses[6]);
    }
}
