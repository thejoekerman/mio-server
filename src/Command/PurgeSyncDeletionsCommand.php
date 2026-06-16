<?php

namespace App\Command;

use App\Repository\SyncDeletionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:purge-deletions',
    description: 'Remove expired sync deletion markers and advance affected users cursor floors.',
)]
class PurgeSyncDeletionsCommand extends Command
{
    public function __construct(
        private readonly SyncDeletionRepository $deletionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Keep deletion markers for this many days.', '180');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT);

        if (false === $days || $days < 1) {
            $io->error('The retention period must be a positive number of days.');

            return Command::INVALID;
        }

        $expired = $this->deletionRepository->findExpired(new \DateTimeImmutable(sprintf('-%d days', $days)));

        foreach ($expired as $deletion) {
            $deletion->getUser()?->advanceMinimumSupportedCursor($deletion->getRevision());
            $this->entityManager->remove($deletion);
        }

        $this->entityManager->flush();
        $io->success(sprintf('Purged %d sync deletion marker(s).', count($expired)));

        return Command::SUCCESS;
    }
}
