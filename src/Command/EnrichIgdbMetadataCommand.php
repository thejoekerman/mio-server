<?php

namespace App\Command;

use App\Entity\Game;
use App\IGDB\IgdbClient;
use App\Repository\GameRepository;
use App\Service\IgdbMetadataEnricher;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:igdb:enrich',
    description: 'Fetch missing IGDB metadata for games with a manual IGDB ID.',
)]
final class EnrichIgdbMetadataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRepository $gameRepository,
        private readonly IgdbClient $igdbClient,
        private readonly IgdbMetadataEnricher $igdbMetadataEnricher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum games to enrich in this run.', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));

        $authenticationError = $this->igdbClient->authenticationError();

        if (null !== $authenticationError) {
            $io->error(sprintf('IGDB enrichment authentication failed. %s', $authenticationError));

            return Command::FAILURE;
        }

        $io->writeln('IGDB credentials are configured and Twitch authentication succeeded.');

        $games = array_slice($this->gameRepository->findMissingIgdbMetadata(), 0, $limit);

        if ([] === $games) {
            $io->success('No games need IGDB enrichment.');

            return Command::SUCCESS;
        }

        $enriched = 0;

        foreach ($games as $game) {
            $previousMetadata = $this->metadataFingerprint($game);

            $this->igdbMetadataEnricher->enrich($game);

            if ($previousMetadata !== $this->metadataFingerprint($game)) {
                ++$enriched;
                $io->writeln(sprintf('Enriched %s (%s)', $game->getTitle(), $game->getIgdbId()));
            } else {
                $io->warning(sprintf('No IGDB metadata changes for %s (%s).', $game->getTitle(), $game->getIgdbId()));
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('Enriched %d of %d candidate games.', $enriched, count($games)));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFingerprint(Game $game): array
    {
        return [
            'igdbUrl' => $game->getIgdbUrl(),
            'coverUrl' => $game->getCoverUrl(),
            'igdbTtbHastilySeconds' => $game->getIgdbTtbHastilySeconds(),
            'igdbTtbNormallySeconds' => $game->getIgdbTtbNormallySeconds(),
            'igdbTtbCompletelySeconds' => $game->getIgdbTtbCompletelySeconds(),
            'igdbTtbCount' => $game->getIgdbTtbCount(),
            'igdbTtbUpdatedAt' => $game->getIgdbTtbUpdatedAt()?->format(DateTimeInterface::ATOM),
            'igdbDevelopers' => $game->getIgdbDevelopers(),
            'igdbPublishers' => $game->getIgdbPublishers(),
            'igdbThemes' => $game->getIgdbThemes(),
            'igdbGameModes' => $game->getIgdbGameModes(),
        ];
    }
}
