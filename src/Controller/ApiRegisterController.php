<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\AddressRepository;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApiRegisterController extends AbstractController
{
    public function __invoke(
        Request $request,
        CustomerRepository $customerRepository,
        AddressRepository $addressRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtTokenManager,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON payload.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $requiredFields = ['email', 'password', 'firstname', 'lastname', 'birthDate', 'address'];
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                return $this->json(['message' => sprintf('Field "%s" is required.', $field)], JsonResponse::HTTP_BAD_REQUEST);
            }
        }

        $email = strtolower(trim((string) $payload['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Invalid email format.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($customerRepository->findOneBy(['email' => $email]) !== null) {
            return $this->json(['message' => 'An account already exists with this email.'], JsonResponse::HTTP_CONFLICT);
        }

        $password = (string) $payload['password'];
        if (mb_strlen($password) < 8) {
            return $this->json(['message' => 'Password must be at least 8 characters long.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $birthDate = new \DateTimeImmutable((string) $payload['birthDate']);
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid birthDate format.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $addressReference = $payload['address'];
        $addressId = null;

        if (is_numeric($addressReference)) {
            $addressId = (int) $addressReference;
        }

        if (is_string($addressReference) && preg_match('#^/api/addresses/(\d+)$#', $addressReference, $matches) === 1) {
            $addressId = (int) $matches[1];
        }

        if ($addressId === null) {
            return $this->json(['message' => 'Invalid address reference. Use an address ID or IRI (/api/addresses/{id}).'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $address = $addressRepository->find($addressId);
        if ($address === null) {
            return $this->json(['message' => 'Address not found.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $customer = (new Customer())
            ->setEmail($email)
            ->setFirstname((string) $payload['firstname'])
            ->setLastname((string) $payload['lastname'])
            ->setBirthDate($birthDate)
            ->setAddress($address)
            ->setRoles(['ROLE_CUSTOMER']);

        $customer->setPassword($passwordHasher->hashPassword($customer, $password));

        $entityManager->persist($customer);
        $entityManager->flush();

        return $this->json([
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'token' => $jwtTokenManager->create($customer),
        ], JsonResponse::HTTP_CREATED);
    }
}
