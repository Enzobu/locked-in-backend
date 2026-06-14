<?php

namespace App\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiCustomerPasswordController extends AbstractController
{
    private const MIN_PASSWORD_LENGTH = 8;

    #[Route('/api/customers/me/password', name: 'api_customer_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $customer = $this->getUser();
        if (!$customer instanceof Customer) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $currentPassword = $payload['currentPassword'] ?? null;
        $newPassword = $payload['newPassword'] ?? null;

        if (!is_string($currentPassword) || !is_string($newPassword) || $currentPassword === '' || $newPassword === '') {
            return $this->json(['message' => 'currentPassword and newPassword are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$passwordHasher->isPasswordValid($customer, $currentPassword)) {
            return $this->json(['message' => 'Current password is incorrect.'], Response::HTTP_FORBIDDEN);
        }

        if (mb_strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return $this->json(
                ['message' => sprintf('New password must be at least %d characters long.', self::MIN_PASSWORD_LENGTH)],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($passwordHasher->isPasswordValid($customer, $newPassword)) {
            return $this->json(['message' => 'New password must be different from the current one.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $customer->setPassword($passwordHasher->hashPassword($customer, $newPassword));
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
