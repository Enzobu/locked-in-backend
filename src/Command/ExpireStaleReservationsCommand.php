<?php

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\Reservation\ReservationLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Expires unpaid PENDING reservations that have been sitting around for too long
 * and frees their lockers, so a payment that is never completed does not block a
 * locker forever.
 *
 * Meant to be run periodically (cron / Symfony Scheduler), e.g. every 5 minutes:
 *   * /5 * * * *  php bin/console app:reservations:expire
 */
#[AsCommand(
    name: 'app:reservations:expire',
    description: 'Expire stale unpaid PENDING reservations and free their lockers',
)]
final class ExpireStaleReservationsCommand extends Command
{
    private const DEFAULT_AGE_MINUTES = 30;

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationLifecycleService $lifecycleService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'older-than',
            null,
            InputOption::VALUE_REQUIRED,
            'Only expire PENDING reservations created at least this many minutes ago',
            (string) self::DEFAULT_AGE_MINUTES,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $minutes = max(0, (int) $input->getOption('older-than'));
        $threshold = (new \DateTimeImmutable())->modify(sprintf('-%d minutes', $minutes));

        $candidates = $this->reservationRepository->findExpirablePending($threshold);

        $expired = 0;
        foreach ($candidates as $reservation) {
            if ($this->lifecycleService->expire($reservation)) {
                ++$expired;
            }
        }

        if ($expired > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('Expired %d reservation(s) older than %d minute(s).', $expired, $minutes));

        return Command::SUCCESS;
    }
}
