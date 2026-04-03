<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Locker;
use App\Service\Locker\LockerCommandService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiLockerController extends AbstractController
{
    #[Route('/api/locker/{id}/open', name: 'app_api_locker_open', methods: ['GET'])]
    public function open(
        Locker $locker,
        LockerCommandService $lockerCommandService,
    ): JsonResponse {
        $result = $lockerCommandService->open($locker);

        return $this->json($result->data, $result->statusCode);
    }
}
