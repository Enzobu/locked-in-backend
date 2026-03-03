<?php

namespace App\Controller;

use App\Entity\Customer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiCustomerMeController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $addresses = [];
        foreach ($user->getAddresses() as $address) {
            $addresses[] = [
                'id' => $address->getId(),
                'number' => $address->getNumber(),
                'street' => $address->getStreet(),
                'city' => $address->getCity(),
                'country' => $address->getCountry(),
                'complement' => $address->getComplement(),
            ];
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'birthDate' => $user->getBirthDate()?->format(\DateTimeInterface::ATOM),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'addresses' => $addresses,
        ]);
    }
}
