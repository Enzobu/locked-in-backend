<?php

namespace App\Controller;

use App\Entity\LockerAction;
use App\Entity\LockerEvent;
use App\Entity\User;
use App\Repository\LockerRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/{id}/price', name: 'app_admin_lockers_update_price', methods: ['POST'])]
    public function updatePrice(
        int $id,
        Request $request,
        LockerRepository $lockerRepository,
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

        if (!$this->isCsrfTokenValid('update_price_'.$locker->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $locker->getLockerBay()?->getId()]);
        }

        $priceEuroRaw = trim((string) $request->request->get('priceEuro', ''));
        $priceEuroSanitized = preg_replace('/[^0-9,\.\-]/', '', $priceEuroRaw) ?? '';
        $priceEuroNormalized = str_replace(',', '.', $priceEuroSanitized);

        if ($priceEuroNormalized === '' || !is_numeric($priceEuroNormalized)) {
            $this->addFlash('danger', 'Prix invalide.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $locker->getLockerBay()?->getId()]);
        }

        $priceCents = (int) round(((float) $priceEuroNormalized) * 100);
        if ($priceCents < 0) {
            $this->addFlash('danger', 'Le prix doit etre positif.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $locker->getLockerBay()?->getId()]);
        }

        $locker->setPriceCents($priceCents);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Prix du casier #%d mis a jour.', $locker->getNumber()));

        $referer = (string) $request->headers->get('referer', '');
        if (str_contains($referer, '/admin/lockers/')) {
            return $this->redirectToRoute('app_admin_lockers_show', ['id' => $locker->getId()]);
        }

        return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $locker->getLockerBay()?->getId()]);
    }
}
