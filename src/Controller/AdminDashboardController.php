<?php

namespace App\Controller;

use App\Entity\LockerAction;
use App\Entity\LockerEvent;
use App\Enum\LockerStatus;
use App\Enum\ReservationStatus;
use App\Repository\CustomerRepository;
use App\Repository\LockerBayRepository;
use App\Repository\LockerRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(
        LockerRepository $lockerRepository,
        LockerBayRepository $lockerBayRepository,
        ReservationRepository $reservationRepository,
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $totalLockers = $lockerRepository->count([]);
        $availableLockers = $lockerRepository->count(['status' => LockerStatus::AVAILABLE]);
        $occupiedLockers = $lockerRepository->count(['status' => LockerStatus::OCCUPIED]);
        $reservedLockers = $lockerRepository->count(['status' => LockerStatus::RESERVED]);
        $offlineLockers = $lockerRepository->count(['status' => LockerStatus::OFFLINE]);
        $outOfOrderLockers = $lockerRepository->count(['status' => LockerStatus::OUT_OF_ORDER]);

        $totalReservations = $reservationRepository->count([]);
        $activeReservations = $reservationRepository->count(['status' => ReservationStatus::ACTIVE]);
        $confirmedReservations = $reservationRepository->count(['status' => ReservationStatus::CONFIRMED]);

        $upcomingReservations = $reservationRepository->createQueryBuilder('r')
            ->leftJoin('r.customer', 'c')->addSelect('c')
            ->leftJoin('r.locker', 'l')->addSelect('l')
            ->leftJoin('l.lockerBay', 'b')->addSelect('b')
            ->where('r.startsAt >= :now')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('statuses', [ReservationStatus::CONFIRMED, ReservationStatus::ACTIVE])
            ->orderBy('r.startsAt', 'ASC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $lockerAlerts = $lockerRepository->findBy(
            ['status' => [LockerStatus::OFFLINE, LockerStatus::OUT_OF_ORDER]],
            ['updatedAt' => 'DESC'],
            6,
        );

        $recentActions = $entityManager->getRepository(LockerAction::class)->findBy([], ['requestedAt' => 'DESC'], 6);
        $recentEvents = $entityManager->getRepository(LockerEvent::class)->findBy([], ['occurredAt' => 'DESC'], 6);

        return $this->render('admin/dashboard.html.twig', [
            'totalLockers' => $totalLockers,
            'availableLockers' => $availableLockers,
            'occupiedLockers' => $occupiedLockers,
            'reservedLockers' => $reservedLockers,
            'offlineLockers' => $offlineLockers,
            'outOfOrderLockers' => $outOfOrderLockers,
            'occupancyRate' => $totalLockers > 0 ? (int) round((($occupiedLockers + $reservedLockers) / $totalLockers) * 100) : 0,
            'totalLockerBays' => $lockerBayRepository->count([]),
            'totalCustomers' => $customerRepository->count(['isDeleted' => false]),
            'totalReservations' => $totalReservations,
            'activeReservations' => $activeReservations,
            'confirmedReservations' => $confirmedReservations,
            'upcomingReservations' => $upcomingReservations,
            'lockerAlerts' => $lockerAlerts,
            'recentActions' => $recentActions,
            'recentEvents' => $recentEvents,
        ]);
    }
}
