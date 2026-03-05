<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Locker;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

final class LockerCollectionFilterExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Locker::class) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $lockerBayId = $request?->query->get('lockerBayId');

        if (!is_string($lockerBayId) || $lockerBayId === '' || !ctype_digit($lockerBayId)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0] ?? 'o';

        $queryBuilder
            ->andWhere(sprintf('IDENTITY(%s.lockerBay) = :lockerBayId', $rootAlias))
            ->setParameter('lockerBayId', (int) $lockerBayId);
    }
}
