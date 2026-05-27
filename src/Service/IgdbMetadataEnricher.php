<?php

namespace App\Service;

use App\Entity\Game;
use App\IGDB\IgdbClient;

final readonly class IgdbMetadataEnricher
{
    public function __construct(
        private IgdbClient $igdbClient,
    ) {
    }

    public function enrich(Game $game): void
    {
        $igdbId = $game->getIgdbId();

        if (
            null === $igdbId
            || (
                null !== $game->getCoverUrl()
                && null !== $game->getIgdbUrl()
                && null !== $game->getIgdbTtbCount()
                && null !== $game->getIgdbDevelopers()
                && null !== $game->getIgdbPublishers()
                && null !== $game->getIgdbThemes()
                && null !== $game->getIgdbGameModes()
                && null !== $game->getReleaseYear()
            )
        ) {
            return;
        }

        $didWrite = false;

        if (
            null === $game->getCoverUrl()
            || null === $game->getIgdbUrl()
            || null === $game->getIgdbDevelopers()
            || null === $game->getIgdbPublishers()
            || null === $game->getIgdbThemes()
            || null === $game->getIgdbGameModes()
            || null === $game->getReleaseYear()
        ) {
            $metadata = $this->igdbClient->fetchGame($igdbId);

            if (null !== $metadata) {
                $year = $metadata->firstReleaseDate ? (int) $metadata->firstReleaseDate->format('Y') : null;
                $now = new \DateTimeImmutable();
                $game
                    ->setIgdbUrl($metadata->url)
                    ->setCoverUrl($metadata->coverUrl())
                    ->setIgdbDevelopers($metadata->developers)
                    ->setIgdbPublishers($metadata->publishers)
                    ->setIgdbThemes($metadata->themes)
                    ->setIgdbGameModes($metadata->gameModes)
                    ->setReleaseYear($year)
                    ->setReleaseYearUpdatedAt($now);
                $didWrite = true;
            }
        }

        if (null === $game->getIgdbTtbCount()) {
            $timeToBeat = $this->igdbClient->fetchTimeToBeat($igdbId);

            if (null !== $timeToBeat) {
                $game
                    ->setIgdbTtbHastilySeconds($timeToBeat->hastilySeconds)
                    ->setIgdbTtbNormallySeconds($timeToBeat->normallySeconds)
                    ->setIgdbTtbCompletelySeconds($timeToBeat->completelySeconds)
                    ->setIgdbTtbCount($timeToBeat->count)
                    ->setIgdbTtbUpdatedAt($timeToBeat->updatedAt);
                $didWrite = true;
            }
        }

        if ($didWrite) {
            $game->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
