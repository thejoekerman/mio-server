<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:list',
    description: 'List users and administrative flags.',
)]
class ListUsersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $users = $this->userRepository->findBy([], ['id' => 'ASC']);

        if ([] === $users) {
            $io->warning('No users found.');

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->getId(),
                $user->getEmail() ?? '',
                $user->getDisplayName() ?? '',
                $user->getAiUsage() ? 'yes' : 'no',
            ];
        }

        $io->table(
            ['ID', 'Email', 'Display name', 'AI usage'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
