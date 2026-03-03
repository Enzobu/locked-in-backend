<?php

namespace App\Controller;

use App\Entity\LockerAction;
use App\Entity\LockerEvent;
use App\Entity\User;
use App\Repository\LockerRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lockers')]
class AdminLockerController extends AbstractController
{
    #[Route('/{id}', name: 'app_admin_lockers_show', methods: ['GET'])]
    public function show(
        int $id,
        LockerRepository $lockerRepository,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $locker = $lockerRepository->findOneVisibleForBackoffice($id, $user);
        if ($locker === null) {
            throw $this->createNotFoundException('Casier introuvable.');
        }

        $now = new \DateTimeImmutable();

        $upcomingReservations = $reservationRepository->findUpcomingForLocker($locker, $now, 8);
        $currentReservations = $reservationRepository->findCurrentForLocker($locker, $now);
        $historyReservations = $reservationRepository->findHistoryForLocker($locker, $now, 20);

        $recentActions = $entityManager->getRepository(LockerAction::class)->findBy(['locker' => $locker], ['requestedAt' => 'DESC'], 8);
        $recentEvents = $entityManager->getRepository(LockerEvent::class)->findBy(['locker' => $locker], ['occurredAt' => 'DESC'], 8);

        return $this->render('admin/lockers/show.html.twig', [
            'locker' => $locker,
            'upcomingReservations' => $upcomingReservations,
            'currentReservations' => $currentReservations,
            'historyReservations' => $historyReservations,
            'recentActions' => $recentActions,
            'recentEvents' => $recentEvents,
        ]);
    }
}
