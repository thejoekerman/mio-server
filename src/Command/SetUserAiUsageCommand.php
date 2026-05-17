<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:ai-usage',
    description: 'Enable or disable AI features for a user.',
)]
class SetUserAiUsageCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'User id')
            ->addArgument('enabled', InputArgument::REQUIRED, 'Allow AI features: yes or no');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = trim((string) $input->getArgument('id'));
        $enabled = filter_var($input->getArgument('enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ('' === $id || !ctype_digit($id) || (int) $id < 1) {
            $io->error('User id must be a positive integer.');

            return Command::INVALID;
        }

        if (null === $enabled) {
            $io->error('AI usage must be one of: yes, no, true, false, 1, 0.');

            return Command::INVALID;
        }

        $user = $this->userRepository->find((int) $id);

        if (!$user instanceof User) {
            $io->error(sprintf('User #%d was not found.', (int) $id));

            return Command::FAILURE;
        }

        $user->setAiUsage($enabled);
        $this->entityManager->flush();

        $io->success(sprintf(
            'AI usage %s for user #%d.',
            $enabled ? 'enabled' : 'disabled',
            (int) $id,
        ));

        return Command::SUCCESS;
    }
}
