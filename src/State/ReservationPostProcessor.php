<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Locker;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use App\Repository\CustomerRepository;
use App\Service\Reservation\ReservationAvailabilityChecker;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<Reservation, Reservation>
 */
final class ReservationPostProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly RequestStack $requestStack,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly CustomerRepository $customerRepository,
        private readonly ReservationAvailabilityChecker $availabilityChecker,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Reservation $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Reservation) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $request = $this->requestStack->getCurrentRequest();
        $authorizationHeader = $request?->headers->get('Authorization');

        if (!is_string($authorizationHeader) || preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches) !== 1) {
            throw new UnauthorizedHttpException('Bearer', 'Missing or invalid Bearer token.');
        }

        try {
            $payload = $this->jwtTokenManager->parse($matches[1]);
        } catch (\Throwable) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid JWT token.');
        }

        $claim = (string) $this->jwtTokenManager->getUserIdClaim();
        $identifier = $payload[$claim] ?? null;

        if (!is_string($identifier) || trim($identifier) === '') {
            throw new UnauthorizedHttpException('Bearer', 'JWT payload does not contain a valid customer identifier.');
        }

        $customer = $this->customerRepository->findOneBy(['email' => strtolower(trim($identifier))]);

        if ($customer === null || $customer->isDeleted()) {
            throw new UnauthorizedHttpException('Bearer', 'Authenticated customer not found or disabled.');
        }

        $data->setCustomer($customer);

        $locker = $data->getLocker();
        if (!$locker instanceof Locker) {
            throw new UnprocessableEntityHttpException('locker is required.');
        }

        // A reservation always starts its life as PENDING; clients cannot self-assign another status.
        $data->setStatus(ReservationStatus::PENDING);

        // Lock the locker row + validate availability + persist atomically so two
        // concurrent requests cannot both book an overlapping slot.
        return $this->entityManager->wrapInTransaction(function () use ($data, $locker, $operation, $uriVariables, $context) {
            $this->availabilityChecker->lockLocker($locker);
            $this->availabilityChecker->assertBookable($locker, $data->getStartsAt(), $data->getEndsAt());

            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        });
    }
}
