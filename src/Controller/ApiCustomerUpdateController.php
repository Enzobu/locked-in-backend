<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Customer;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiCustomerUpdateController extends AbstractController
{
    public function __invoke(Request $request, AddressRepository $addressRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Customer) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('firstname', $payload) && is_string($payload['firstname'])) {
            $user->setFirstname($payload['firstname']);
        }

        if (array_key_exists('lastname', $payload) && is_string($payload['lastname'])) {
            $user->setLastname($payload['lastname']);
        }

        if (array_key_exists('email', $payload) && is_string($payload['email'])) {
            $user->setEmail($payload['email']);
        }

        if (array_key_exists('birthDate', $payload) && is_string($payload['birthDate'])) {
            try {
                $user->setBirthDate(new \DateTimeImmutable($payload['birthDate']));
            } catch (\Throwable) {
                return $this->json(['message' => 'Invalid birthDate format.'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (array_key_exists('address', $payload)) {
            $address = $this->resolveAddress($payload['address'], $addressRepository);
            if (!$address instanceof Address) {
                return $this->json(['message' => 'Invalid address reference.'], Response::HTTP_BAD_REQUEST);
            }

            $user->setAddress($address);
        }

        $entityManager->flush();

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

    private function resolveAddress(mixed $addressReference, AddressRepository $addressRepository): ?Address
    {
        if (is_numeric($addressReference)) {
            return $addressRepository->find((int) $addressReference);
        }

        if (is_string($addressReference) && preg_match('#^/api/addresses/(\d+)$#', $addressReference, $matches) === 1) {
            return $addressRepository->find((int) $matches[1]);
        }

        return null;
    }
}
