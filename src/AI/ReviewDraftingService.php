<?php

namespace App\AI;

use App\Entity\Game;
use App\Entity\LogEntry;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class ReviewDraftingService
{
    public function __construct(
        #[Target('review_drafter')]
        private AgentInterface $reviewDrafter,
        #[Target('review_drafter_gemini')]
        private AgentInterface $reviewDrafterGemini,
    ) {
    }

    public function draftReview(Game $game, string $provider = 'lmstudio'): string
    {
        $agent = match ($provider) {
            'lmstudio' => $this->reviewDrafter,
            'gemini' => $this->reviewDrafterGemini,
            default => throw new \InvalidArgumentException(sprintf('Unsupported review draft provider "%s".', $provider)),
        };

        $result = $agent->call(new MessageBag(Message::ofUser($this->buildPromptInput($game))));

        if (!$result instanceof TextResult) {
            throw new \RuntimeException('Review drafter did not return text output.');
        }

        return trim($result->getContent());
    }

    private function buildPromptInput(Game $game): string
    {
        $lines = [
            'Draft a review for this game from the following MioLog context.',
            '',
            'Game metadata:',
            sprintf('- Title: %s', $game->getTitle()),
            sprintf('- Status: %s', $game->getStatus()),
            sprintf('- Platform: %s', $this->valueOrFallback($game->getPlatform(), 'unspecified')),
            sprintf('- Tags: %s', $game->getTags() !== [] ? implode(', ', $game->getTags()) : 'none'),
            sprintf('- Rating: %s', null !== $game->getRating() ? (string) $game->getRating() : 'none'),
            sprintf('- Play time hours: %s', null !== $game->getPlayTimeHours() ? $game->getPlayTimeHours() : 'unknown'),
            sprintf('- Finished on: %s', null !== $game->getFinishedAt() ? $game->getFinishedAt()->format('Y-m-d') : 'not finished / unknown'),
        ];

        $existingReview = trim($game->getReview());
        if ('' !== $existingReview) {
            $lines[] = sprintf('- Existing review notes: %s', $existingReview);
        }

        $logs = $this->formatLogs($game);
        $lines[] = '';
        $lines[] = 'Play logs:';
        $lines[] = [] !== $logs ? implode("\n", $logs) : '- No play logs were provided.';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function formatLogs(Game $game): array
    {
        $entries = array_filter(
            $game->getLogEntries()->toArray(),
            static fn (LogEntry $entry): bool => null === $entry->getDeletedAt(),
        );

        usort(
            $entries,
            static fn (LogEntry $left, LogEntry $right): int => $left->getCreatedAt() <=> $right->getCreatedAt(),
        );

        return array_map(
            static fn (LogEntry $entry): string => sprintf(
                '- [%s] %s',
                $entry->getCreatedAt()->format('Y-m-d H:i'),
                trim($entry->getContent()),
            ),
            $entries,
        );
    }

    private function valueOrFallback(string $value, string $fallback): string
    {
        $trimmed = trim($value);

        return '' !== $trimmed ? $trimmed : $fallback;
    }
}
