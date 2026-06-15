<?php

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

final class HomeControllerTest extends AbstractApiTestCase
{
    public function testHomeRedirectsToAdminDashboard(): void
    {
        self::assertSame(Response::HTTP_FOUND, $this->client->request('GET', '/')->getStatusCode());
    }
}
