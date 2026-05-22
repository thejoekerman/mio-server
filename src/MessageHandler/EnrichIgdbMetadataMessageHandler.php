<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Exception\EnrichAlreadyRunningException;
use App\IGDB\IgdbClient;
use App\Message\EnrichIgdbMetadataMessage;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Service\IgdbMetadataEnricher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EnrichIgdbMetadataMessageHandler
{
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRepository $gameRepository,
        private readonly UserRepository $userRepository,
        private readonly IgdbClient $igdbClient,
        private readonly IgdbMetadataEnricher $igdbMetadataEnricher,
    ) {
    }

    public function __invoke(EnrichIgdbMetadataMessage $message): void
    {
        $user = $this->userRepository->find($message->userId);

        if (!$user instanceof User) {
            return;
        }

        $lock = $this->lockFactory->createLock(sprintf('igdb-enrich-%d', $message->userId));

        if (false === $lock->acquire()) {
            throw new EnrichAlreadyRunningException();
        }

        try {
            $authenticationError = $this->igdbClient->authenticationError();

            if (null !== $authenticationError) {
                throw new \RuntimeException(sprintf('IGDB authentication failed: %s', $authenticationError));
            }

            $games = $this->gameRepository->findMissingIgdbMetadataForUser($user, $message->limit);

            foreach ($games as $game) {
                $this->igdbMetadataEnricher->enrich($game);
            }

            $this->entityManager->flush();
        } finally {
            $lock->release();
        }
    }
}
