<?php

namespace App\Command;

use App\Entity\SyncToken;
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
    name: 'app:sync-token:create',
    description: 'Create a sync token for a user.',
)]
class CreateSyncTokenCommand extends Command
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
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('token-name', InputArgument::REQUIRED, 'Token name, for example iPhone')
            ->addArgument('display-name', InputArgument::OPTIONAL, 'Optional display name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getArgument('email'));
        $tokenName = trim((string) $input->getArgument('token-name'));
        $displayName = trim((string) $input->getArgument('display-name'));

        if ($email === '' || $tokenName === '') {
            $io->error('Email and token name are required.');

            return Command::INVALID;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email)
                ->setDisplayName($displayName !== '' ? $displayName : $email);

            $this->entityManager->persist($user);
        } elseif ($displayName !== '') {
            $user->setDisplayName($displayName);
        }

        $plainToken = bin2hex(random_bytes(32));

        $syncToken = (new SyncToken())
            ->setUser($user)
            ->setName($tokenName)
            ->setTokenHash(SyncToken::hashPlainToken($plainToken));

        $this->entityManager->persist($syncToken);
        $this->entityManager->flush();

        $io->success('Sync token created.');
        $io->section('Use this token now. It will not be shown again.');
        $io->writeln($plainToken);

        return Command::SUCCESS;
    }
}
