<?php

declare(strict_types=1);

namespace App\Service\Locker;

use App\Entity\Locker;
use App\WebSocket\PendingCommandRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

final class WebSocketLockerGateway implements LockerGatewayInterface
{
    public function __construct(
        private readonly PendingCommandRegistry $pendingCommandRegistry,
        #[Autowire('%env(string:LOCKER_WS_DEFAULT_DEVICE_ID)%')]
        private readonly string $defaultDeviceId,
    ) {
    }

    public function open(Locker $locker): LockerCommandResult
    {
        $deviceId = $this->resolveDeviceId($locker);

        $response = $this->pendingCommandRegistry->dispatchAndWait(
            $deviceId,
            [
                'type' => 'command',
                'action' => 'open',
                'locker' => $locker->getId(),
            ],
            5_000,
        );

        if ($response === null) {
            return new LockerCommandResult(
                Response::HTTP_GATEWAY_TIMEOUT,
                [
                    'status' => 'failed',
                    'error' => 'timeout',
                    'deviceId' => $deviceId,
                    'locker' => $locker->getId(),
                ],
            );
        }

        $status = (string) ($response['status'] ?? 'failed');

        $httpStatusCode = match ($status) {
            'opened', 'already_open' => Response::HTTP_OK,
            'failed' => Response::HTTP_INTERNAL_SERVER_ERROR,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        return new LockerCommandResult($httpStatusCode, $response);
    }

    private function resolveDeviceId(Locker $locker): string
    {
        if (method_exists($locker, 'getDeviceId')) {
            $deviceId = (string) $locker->getDeviceId();

            if ($deviceId !== '') {
                return $deviceId;
            }
        }

        return $this->defaultDeviceId;
    }
}
