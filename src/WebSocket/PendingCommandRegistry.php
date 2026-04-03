<?php

declare(strict_types=1);

namespace App\WebSocket;

use Redis;
use Symfony\Component\Uid\Uuid;

final class PendingCommandRegistry
{
    private const OUTBOX_PREFIX = 'locker_ws:outbox:';
    private const RESPONSE_PREFIX = 'locker_ws:response:';
    private const REQUEST_TTL_SECONDS = 15;

    public function __construct(
        private readonly Redis $redis,
    ) {
    }

    public function dispatchAndWait(string $deviceId, array $payload, int $timeoutMs = 5000): ?array
    {
        $correlationId = Uuid::v7()->toRfc4122();
        $payload['correlationId'] = $correlationId;
        $payload['deviceId'] = $deviceId;

        $this->redis->rPush(
            $this->getOutboxKey($deviceId),
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
        $this->redis->expire($this->getOutboxKey($deviceId), self::REQUEST_TTL_SECONDS);

        return $this->waitForResponse($correlationId, $timeoutMs);
    }

    public function storeResponse(string $correlationId, array $response): void
    {
        $this->redis->setEx(
            $this->getResponseKey($correlationId),
            self::REQUEST_TTL_SECONDS,
            json_encode($response, JSON_THROW_ON_ERROR)
        );
    }

    public function popNextCommand(string $deviceId): ?array
    {
        $rawPayload = $this->redis->lPop($this->getOutboxKey($deviceId));

        if (!is_string($rawPayload) || $rawPayload === '') {
            return null;
        }

        /** @var array $decoded */
        $decoded = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function waitForResponse(string $correlationId, int $timeoutMs): ?array
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        $responseKey = $this->getResponseKey($correlationId);

        while (microtime(true) < $deadline) {
            $rawResponse = $this->redis->get($responseKey);

            if (is_string($rawResponse) && $rawResponse !== '') {
                $this->redis->del($responseKey);

                /** @var array $decoded */
                $decoded = json_decode($rawResponse, true, 512, JSON_THROW_ON_ERROR);

                return $decoded;
            }

            usleep(100_000);
        }

        return null;
    }

    private function getOutboxKey(string $deviceId): string
    {
        return self::OUTBOX_PREFIX . $deviceId;
    }

    private function getResponseKey(string $correlationId): string
    {
        return self::RESPONSE_PREFIX . $correlationId;
    }
}
