<?php

namespace App\Controller;

use App\Enum\LockerStatus;
use App\Entity\User;
use App\Repository\LockerBayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/locker-bays')]
class AdminLockerBayController extends AbstractController
{
    #[Route('', name: 'app_admin_locker_bays_index', methods: ['GET'])]
    public function index(Request $request, LockerBayRepository $lockerBayRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $q = $request->query->get('q');
        $bays = $lockerBayRepository->findVisibleForBackoffice($user, is_string($q) ? $q : null);

        return $this->render('admin/locker_bays/index.html.twig', [
            'bays' => $bays,
            'q' => $q,
            'isAdmin' => in_array('ROLE_ADMIN', $user->getRoles(), true),
            'statusEnum' => LockerStatus::class,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_locker_bays_show', methods: ['GET'])]
    public function show(int $id, Request $request, LockerBayRepository $lockerBayRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $bay = $lockerBayRepository->findOneVisibleForBackoffice($id, $user);
        if ($bay === null) {
            throw $this->createNotFoundException('Baie introuvable.');
        }

        $q = mb_strtolower(trim((string) $request->query->get('q', '')));
        $status = trim((string) $request->query->get('status', 'all'));

        $lockers = $bay->getLockers()->toArray();
        $filteredLockers = array_filter($lockers, function ($locker) use ($q, $status): bool {
            if ($status !== 'all' && $locker->getStatus()->value !== $status) {
                return false;
            }

            if ($q === '') {
                return true;
            }

            return str_contains((string) $locker->getNumber(), $q)
                || ($locker->getHardwareId() !== null && str_contains(mb_strtolower($locker->getHardwareId()), $q));
        });

        usort($filteredLockers, static fn ($a, $b): int => $a->getNumber() <=> $b->getNumber());

        return $this->render('admin/locker_bays/show.html.twig', [
            'bay' => $bay,
            'lockers' => $filteredLockers,
            'statusFilter' => $status,
            'q' => $request->query->get('q'),
            'statusEnum' => LockerStatus::class,
        ]);
    }

    #[Route('/{id}/durations', name: 'app_admin_locker_bays_update_durations', methods: ['POST'])]
    public function updateDurations(
        int $id,
        Request $request,
        LockerBayRepository $lockerBayRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $bay = $lockerBayRepository->findOneVisibleForBackoffice($id, $user);
        if ($bay === null) {
            throw $this->createNotFoundException('Baie introuvable.');
        }

        if (!$this->isCsrfTokenValid('update_durations_'.$bay->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $id]);
        }

        $minRaw = $request->request->get('minDuration');
        $maxRaw = $request->request->get('maxDuration');

        $minDuration = ($minRaw === null || $minRaw === '') ? null : (int) $minRaw;
        $maxDuration = ($maxRaw === null || $maxRaw === '') ? null : (int) $maxRaw;

        if (($minDuration !== null && $minDuration < 0) || ($maxDuration !== null && $maxDuration < 0)) {
            $this->addFlash('danger', 'Les durees doivent etre positives.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $id]);
        }

        if ($minDuration !== null && $maxDuration !== null && $maxDuration < $minDuration) {
            $this->addFlash('danger', 'La duree max doit etre superieure ou egale a la duree min.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $id]);
        }

        $bay->setMinDuration($minDuration);
        $bay->setMaxDuration($maxDuration);
        $entityManager->flush();

        $this->addFlash('success', 'Durees de la baie mises a jour.');

        return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $id]);
    }
}
