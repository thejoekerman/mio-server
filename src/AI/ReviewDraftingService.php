<?php

namespace App\AI;

use App\Entity\Game;
use App\Entity\Journey;
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
        private string $appEnv,
        private ?string $configuredProvider = null,
    ) {
    }

    public function draftReview(
        Game $game,
        Journey $journey,
        ?string $provider = null,
        string $language = 'en',
    ): string {
        $agent = match ($provider ?? $this->resolveProvider()) {
            'lmstudio' => $this->reviewDrafter,
            'gemini' => $this->reviewDrafterGemini,
            default => throw new \InvalidArgumentException('Unsupported review draft provider.'),
        };

        $result = $agent->call(new MessageBag(Message::ofUser($this->buildPromptInput($game, $journey, $language))));

        if (!$result instanceof TextResult) {
            throw new \RuntimeException('Review drafter did not return text output.');
        }

        return trim($result->getContent());
    }

    private function resolveProvider(): string
    {
        $provider = trim((string) $this->configuredProvider);

        if ('' !== $provider) {
            return $provider;
        }

        return 'dev' === $this->appEnv ? 'lmstudio' : 'gemini';
    }

    private function buildPromptInput(Game $game, Journey $journey, string $language): string
    {
        $lines = [
            'Draft a review for this game from the following MioLog context.',
            sprintf('Write the review draft in %s.', $this->languageLabel($language)),
            '',
            'Game metadata:',
            sprintf('- Title: %s', $game->getTitle()),
            sprintf('- Status: %s', $journey->getStatus()),
            sprintf('- Platform: %s', $this->valueOrFallback($journey->getPlatform(), 'unspecified')),
            sprintf('- Tags: %s', $game->getTags() !== [] ? implode(', ', $game->getTags()) : 'none'),
            sprintf('- Rating: %s', null !== $journey->getRating() ? (string) $journey->getRating() : 'none'),
            sprintf('- Play time hours: %s', null !== $journey->getPlayTimeHours() ? $journey->getPlayTimeHours() : 'unknown'), // phpcs:ignore Generic.Files.LineLength
            sprintf('- Finished on: %s', null !== $journey->getFinishedAt() ? $journey->getFinishedAt()->format('Y-m-d') : 'not finished / unknown'), // phpcs:ignore Generic.Files.LineLength
        ];

        $existingReview = trim($journey->getReview());
        if ('' !== $existingReview) {
            $lines[] = sprintf('- Existing review notes: %s', $existingReview);
        }

        $logs = $this->formatLogs($journey);
        $lines[] = '';
        $lines[] = 'Play logs:';
        $lines[] = [] !== $logs ? implode("\n", $logs) : '- No play logs were provided.';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function formatLogs(Journey $journey): array
    {
        $entries = array_filter(
            $journey->getLogEntries()->toArray(),
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

    private function languageLabel(string $language): string
    {
        return 'de' === $language ? 'German' : 'English';
    }
}
