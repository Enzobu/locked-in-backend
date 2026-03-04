<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Reservation;
use App\Repository\CustomerRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

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

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
