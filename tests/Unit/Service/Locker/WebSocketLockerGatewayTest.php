<?php

namespace App\Tests\Unit\Service\Locker;

use App\Entity\Locker;
use App\Service\Locker\WebSocketLockerGateway;
use App\WebSocket\PendingCommandRegistry;
use PHPUnit\Framework\TestCase;
use Redis;
use Symfony\Component\HttpFoundation\Response;

final class WebSocketLockerGatewayTest extends TestCase
{
    public function testOpenReturnsTimeoutWhenDeviceDoesNotRespond(): void
    {
        $registry = new PendingCommandRegistry($this->redisReturningResponse(null));
        $locker = (new Locker())->setDeviceId('device-a');

        $result = (new WebSocketLockerGateway($registry, 'default-device'))->open($locker);

        self::assertSame(Response::HTTP_GATEWAY_TIMEOUT, $result->statusCode);
        self::assertSame('timeout', $result->data['error']);
        self::assertSame('device-a', $result->data['deviceId']);
    }

    public function testOpenMapsDeviceResponseStatus(): void
    {
        $registry = new PendingCommandRegistry($this->redisReturningResponse(['status' => 'opened']));
        $locker = new Locker();

        $result = (new WebSocketLockerGateway($registry, 'default-device'))->open($locker);

        self::assertSame(Response::HTTP_OK, $result->statusCode);
        self::assertSame(['status' => 'opened'], $result->data);

        $registry = new PendingCommandRegistry($this->redisReturningResponse(['status' => 'failed']));
        $result = (new WebSocketLockerGateway($registry, 'default-device'))->open($locker);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $result->statusCode);
    }

    public function testRegistryStoresAndPopsCommands(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('setEx')->willReturn(true);
        $redis->expects(self::once())->method('lPop')->willReturn(json_encode(['type' => 'command'], JSON_THROW_ON_ERROR));

        $registry = new PendingCommandRegistry($redis);
        $registry->storeResponse('correlation-id', ['status' => 'opened']);

        self::assertSame(['type' => 'command'], $registry->popNextCommand('device-a'));
    }

    public function testPopNextCommandReturnsNullForEmptyQueue(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->method('lPop')->willReturn(false);

        self::assertNull((new PendingCommandRegistry($redis))->popNextCommand('device-a'));
    }

    private function redisReturningResponse(?array $response): Redis
    {
        $redis = $this->createMock(Redis::class);
        $redis->method('rPush')->willReturn(1);
        $redis->method('expire')->willReturn(true);
        $redis->method('get')->willReturn($response === null ? false : json_encode($response, JSON_THROW_ON_ERROR));
        $redis->method('del')->willReturn(1);

        return $redis;
    }
}
