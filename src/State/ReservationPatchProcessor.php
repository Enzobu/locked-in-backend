<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Locker;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use App\Repository\ReservationRepository;
use App\Service\Reservation\ReservationAvailabilityChecker;
use App\Service\Reservation\ReservationLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles PATCH /api/reservations/{id}.
 *
 * Customers may only drive a reservation to CANCELLED (the mobile app sends
 * {"status":"cancelled"}). Every other status transition is rejected so a
 * customer cannot, for instance, self-confirm a reservation without paying.
 * Cancellation side effects (refund + freeing the locker) run through the
 * shared lifecycle service. When a still-active reservation is rescheduled
 * (its window or locker is patched), availability is re-validated so a PATCH
 * cannot be used to bypass the anti double-booking guard.
 *
 * @implements ProcessorInterface<Reservation, Reservation>
 */
final class ReservationPatchProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationLifecycleService $lifecycleService,
        private readonly ReservationAvailabilityChecker $availabilityChecker,
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

        $oldStatus = $this->resolveOriginalStatus($data);
        $newStatus = $data->getStatus();

        if ($oldStatus !== null && $newStatus !== $oldStatus) {
            if ($newStatus !== ReservationStatus::CANCELLED) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'Status transition "%s" -> "%s" is not allowed.',
                    $oldStatus->value,
                    $newStatus->value,
                ));
            }

            // Restore the real current status so the lifecycle guard evaluates the
            // transition from the right state, then perform the cancellation.
            $data->setStatus($oldStatus);
            $this->lifecycleService->cancel($data);

            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Not a cancellation: if the reservation still occupies a slot, re-validate
        // availability (the window or locker may have been patched) excluding itself,
        // under a lock, so a reschedule cannot create an overlap.
        $locker = $data->getLocker();
        if ($locker instanceof Locker && in_array($data->getStatus(), ReservationRepository::ACTIVE_STATUSES, true)) {
            return $this->entityManager->wrapInTransaction(function () use ($data, $locker, $operation, $uriVariables, $context) {
                $this->availabilityChecker->lockLocker($locker);
                $this->availabilityChecker->assertBookable($locker, $data->getStartsAt(), $data->getEndsAt(), $data->getId());

                return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            });
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function resolveOriginalStatus(Reservation $reservation): ?ReservationStatus
    {
        $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($reservation);
        $status = $original['status'] ?? null;

        if ($status instanceof ReservationStatus) {
            return $status;
        }

        if (is_string($status) && $status !== '') {
            return ReservationStatus::tryFrom($status);
        }

        return null;
    }
}
