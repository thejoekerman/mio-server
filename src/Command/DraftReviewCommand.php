<?php

namespace App\Command;

use App\AI\ReviewDraftingService;
use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:draft-review',
    description: 'Draft a review for a game using the configured MioLog AI agent.',
)]
final class DraftReviewCommand extends Command
{
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly ReviewDraftingService $reviewDraftingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('gameId', InputArgument::REQUIRED, 'The UUID of the game to draft a review for.')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'The drafting provider to use (lmstudio or gemini).', 'lmstudio');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $gameId = (string) $input->getArgument('gameId');
        $provider = (string) $input->getOption('provider');
        $game = $this->gameRepository->find($gameId);

        if (null === $game) {
            $io->error(sprintf('Game "%s" was not found.', $gameId));

            return Command::FAILURE;
        }

        $io->title(sprintf('Review Draft For %s (%s)', $game->getTitle(), $provider));
        $io->writeln($this->reviewDraftingService->draftReview($game, $provider));

        return Command::SUCCESS;
    }
}
