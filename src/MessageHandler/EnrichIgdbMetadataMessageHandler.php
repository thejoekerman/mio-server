<?php

namespace App\MessageHandler;

use App\Exception\EnrichAlreadyRunningException;
use App\IGDB\IgdbClient;
use App\Message\EnrichIgdbMetadataMessage;
use App\Repository\GameRepository;
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
        private readonly IgdbClient $igdbClient,
        private readonly IgdbMetadataEnricher $igdbMetadataEnricher,
    ) {
    }

    public function __invoke(EnrichIgdbMetadataMessage $message): void
    {
        $lock = $this->lockFactory->createLock('igdb-enrich');

        if (false === $lock->acquire()) {
            throw new EnrichAlreadyRunningException();
        }

        try {
            $authenticationError = $this->igdbClient->authenticationError();

            if (null !== $authenticationError) {
                throw new \RuntimeException(sprintf('IGDB authentication failed: %s', $authenticationError));
            }

            $games = $this->gameRepository->findMissingIgdbMetadata();

            foreach ($games as $game) {
                $this->igdbMetadataEnricher->enrich($game);
            }

            $this->entityManager->flush();
        } finally {
            $lock->release();
        }
    }
}
