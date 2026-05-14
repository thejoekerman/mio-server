<?php

namespace App\AI;

use App\Entity\Game;
use App\Entity\LogEntry;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class PlayNextRecommendationService
{
    private const MAX_CANDIDATES = 20;

    public function __construct(
        #[Target('play_next_suggester')]
        private AgentInterface $playNextSuggester,
        #[Target('play_next_suggester_gemini')]
        private AgentInterface $playNextSuggesterGemini,
        private string $appEnv,
        private ?string $configuredProvider = null,
    ) {
    }

    /**
     * @param list<Game> $libraryGames
     */
    /**
     * @param list<Game> $libraryGames
     * @return list<PlayNextRecommendation>
     */
    public function recommend(array $libraryGames, ?string $provider = null, string $language = 'en'): array
    {
        $backlogCandidates = array_values(array_filter(
            $libraryGames,
            static fn (Game $game): bool => null === $game->getDeletedAt() && 'backlog' === $game->getStatus(),
        ));

        if ([] === $backlogCandidates) {
            throw new \RuntimeException('No backlog candidates are available for a recommendation.');
        }

        $candidates = $this->selectPromptCandidates($libraryGames, $backlogCandidates);

        $agent = match ($provider ?? $this->resolveProvider()) {
            'lmstudio' => $this->playNextSuggester,
            'gemini' => $this->playNextSuggesterGemini,
            default => throw new \InvalidArgumentException('Unsupported play-next provider.'),
        };

        $result = $agent->call(new MessageBag(Message::ofUser($this->buildPromptInput($libraryGames, $candidates, $language))));

        if (!$result instanceof TextResult) {
            throw new \RuntimeException('Play-next suggester did not return text output.');
        }

        return $this->parseRecommendation($result->getContent(), $candidates);
    }

    private function resolveProvider(): string
    {
        $provider = trim((string) $this->configuredProvider);

        if ('' !== $provider) {
            return $provider;
        }

        return 'dev' === $this->appEnv ? 'lmstudio' : 'gemini';
    }

    /**
     * @param list<Game> $libraryGames
     * @param list<Game> $candidates
     */
    private function buildPromptInput(array $libraryGames, array $candidates, string $language): string
    {
        $recentFinished = array_values(array_filter(
            $libraryGames,
            static fn (Game $game): bool => null === $game->getDeletedAt() && 'finished' === $game->getStatus(),
        ));
        $activeGames = array_values(array_filter(
            $libraryGames,
            static fn (Game $game): bool => null === $game->getDeletedAt() && in_array($game->getStatus(), ['playing', 'ongoing', 'paused'], true),
        ));

        usort(
            $recentFinished,
            static fn (Game $left, Game $right): int => ($right->getFinishedAt() ?? $right->getUpdatedAt()) <=> ($left->getFinishedAt() ?? $left->getUpdatedAt()),
        );

        $lines = [
            'Recommend one next game from this MioLog backlog.',
            sprintf('Write recommendation reasons in %s.', $this->languageLabel($language)),
            '',
            'Recent finished games and taste signals:',
        ];

        $finishedSummaries = array_slice(array_map(fn (Game $game): string => $this->formatFinishedGame($game), $recentFinished), 0, 3);
        $lines[] = [] !== $finishedSummaries ? implode("\n", $finishedSummaries) : '- No finished games were provided.';

        $lines[] = '';
        $lines[] = 'Currently active or paused games (not finished; do not treat these as completed prerequisites):';
        $lines[] = [] !== $activeGames
            ? implode("\n", array_map(fn (Game $game): string => $this->formatActiveGame($game), array_slice($activeGames, 0, 4)))
            : '- None were provided.';

        $lines[] = '';
        $lines[] = sprintf('Backlog candidates, shortlisted from %d available backlog games:', count(array_filter(
            $libraryGames,
            static fn (Game $game): bool => null === $game->getDeletedAt() && 'backlog' === $game->getStatus(),
        )));
        $lines[] = implode("\n", array_map(fn (Game $game): string => $this->formatCandidate($game), $candidates));

        $lines[] = '';
        $lines[] = 'Important recommendation goal: choose the backlog game that feels like the best next move right now.';

        return implode("\n", $lines);
    }

    /**
     * @param list<Game> $libraryGames
     * @param list<Game> $backlogCandidates
     * @return list<Game>
     */
    private function selectPromptCandidates(array $libraryGames, array $backlogCandidates): array
    {
        if (count($backlogCandidates) <= self::MAX_CANDIDATES) {
            return $backlogCandidates;
        }

        $recentFinished = array_values(array_filter(
            $libraryGames,
            static fn (Game $game): bool => null === $game->getDeletedAt() && 'finished' === $game->getStatus(),
        ));

        usort(
            $recentFinished,
            static fn (Game $left, Game $right): int => ($right->getFinishedAt() ?? $right->getUpdatedAt()) <=> ($left->getFinishedAt() ?? $left->getUpdatedAt()),
        );

        $recentTasteTags = $this->collectRecentTasteTags(array_slice($recentFinished, 0, 5));
        $selected = [];
        $remaining = $backlogCandidates;

        $this->selectBest($selected, $remaining, 6, fn (Game $game): array => [
            count(array_intersect($this->normalizeValues($game->getTags()), $recentTasteTags)),
            $this->dateScore($game->getCreatedAt()),
            $this->dateScore($game->getUpdatedAt()),
        ]);

        $this->selectBest($selected, $remaining, 5, fn (Game $game): array => [
            $this->dateScore($game->getCreatedAt()),
            $this->dateScore($game->getUpdatedAt()),
        ]);

        $this->selectByGroup($selected, $remaining, 4, fn (Game $game): string => $this->valueOrFallback($game->getPlatform(), 'unspecified'));
        $this->selectByGroup($selected, $remaining, 3, fn (Game $game): string => $this->commitmentBucket($game));
        $this->selectEvenly($selected, $remaining, self::MAX_CANDIDATES - count($selected));

        return array_values($selected);
    }

    /**
     * @param array<string, Game> $selected
     * @param list<Game>          $remaining
     * @param callable(Game): array<int, int> $score
     */
    private function selectBest(array &$selected, array &$remaining, int $limit, callable $score): void
    {
        usort(
            $remaining,
            function (Game $left, Game $right) use ($score): int {
                return $score($right) <=> $score($left);
            },
        );

        $this->takeFromRemaining($selected, $remaining, $limit);
    }

    /**
     * @param array<string, Game> $selected
     * @param list<Game>          $remaining
     * @param callable(Game): string $groupBy
     */
    private function selectByGroup(array &$selected, array &$remaining, int $limit, callable $groupBy): void
    {
        $seenGroups = [];

        foreach ($remaining as $index => $game) {
            if (count($seenGroups) >= $limit) {
                break;
            }

            $group = mb_strtolower(trim($groupBy($game)));

            if (isset($seenGroups[$group])) {
                continue;
            }

            $selected[$game->getId()] = $game;
            $seenGroups[$group] = true;
            unset($remaining[$index]);
        }

        $remaining = array_values($remaining);
    }

    /**
     * @param array<string, Game> $selected
     * @param list<Game>          $remaining
     */
    private function selectEvenly(array &$selected, array &$remaining, int $limit): void
    {
        if ($limit <= 0 || [] === $remaining) {
            return;
        }

        $step = max(1, (int) floor(count($remaining) / $limit));
        $indexes = range(0, count($remaining) - 1, $step);

        foreach ($indexes as $index) {
            if (count($selected) >= self::MAX_CANDIDATES || !isset($remaining[$index])) {
                break;
            }

            $game = $remaining[$index];
            $selected[$game->getId()] = $game;
            unset($remaining[$index]);
        }

        $remaining = array_values($remaining);
    }

    /**
     * @param array<string, Game> $selected
     * @param list<Game>          $remaining
     */
    private function takeFromRemaining(array &$selected, array &$remaining, int $limit): void
    {
        foreach ($remaining as $index => $game) {
            if (count($selected) >= self::MAX_CANDIDATES || $limit <= 0) {
                break;
            }

            $selected[$game->getId()] = $game;
            unset($remaining[$index]);
            --$limit;
        }

        $remaining = array_values($remaining);
    }

    /**
     * @param list<Game> $games
     * @return list<string>
     */
    private function collectRecentTasteTags(array $games): array
    {
        $tags = [];

        foreach ($games as $game) {
            foreach ($this->normalizeValues($game->getTags()) as $tag) {
                $tags[$tag] = true;
            }
        }

        return array_keys($tags);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function normalizeValues(array $values): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            $values,
        )));
    }

    private function dateScore(\DateTimeImmutable $date): int
    {
        return $date->getTimestamp();
    }

    private function commitmentBucket(Game $game): string
    {
        $playTime = $game->getPlayTimeHours();

        if (null === $playTime) {
            return 'unknown';
        }

        $hours = (float) $playTime;

        return match (true) {
            $hours <= 8.0 => 'short',
            $hours <= 25.0 => 'medium',
            default => 'long',
        };
    }

    private function formatFinishedGame(Game $game): string
    {
        $lines = [
            sprintf('- %s', $game->getTitle()),
            sprintf('  platform: %s', $this->valueOrFallback($game->getPlatform(), 'unspecified')),
            sprintf('  tags: %s', [] !== $game->getTags() ? implode(', ', $game->getTags()) : 'none'),
            sprintf('  rating: %s', null !== $game->getRating() ? (string) $game->getRating() : 'none'),
        ];

        $review = trim($game->getReview());
        if ('' !== $review) {
            $lines[] = sprintf('  review: %s', $this->truncate($review, 180));
        }

        $logs = $this->formatLogHighlights($game, 1);
        if ([] !== $logs) {
            $lines[] = sprintf('  recent logs: %s', implode(' | ', $logs));
        }

        return implode("\n", $lines);
    }

    private function formatCandidate(Game $game): string
    {
        return sprintf(
            '- id=%s | title=%s | platform=%s | tags=%s | playTime=%s',
            $game->getId(),
            $game->getTitle(),
            $this->valueOrFallback($game->getPlatform(), 'unspecified'),
            [] !== $game->getTags() ? implode(', ', $game->getTags()) : 'none',
            null !== $game->getPlayTimeHours() ? $game->getPlayTimeHours().' h' : 'unknown',
        );
    }

    private function formatActiveGame(Game $game): string
    {
        return sprintf(
            '- %s | status=%s | completion=not finished in MioLog | platform=%s',
            $game->getTitle(),
            $game->getStatus(),
            $this->valueOrFallback($game->getPlatform(), 'unspecified'),
        );
    }

    /**
     * @return list<string>
     */
    private function formatLogHighlights(Game $game, int $limit): array
    {
        $entries = array_values(array_filter(
            $game->getLogEntries()->toArray(),
            static fn (LogEntry $entry): bool => null === $entry->getDeletedAt(),
        ));

        usort(
            $entries,
            static fn (LogEntry $left, LogEntry $right): int => $right->getCreatedAt() <=> $left->getCreatedAt(),
        );

        return array_map(
            fn (LogEntry $entry): string => $this->truncate(trim($entry->getContent()), 120),
            array_slice($entries, 0, $limit),
        );
    }

    /**
     * @param list<Game> $candidates
     * @return list<PlayNextRecommendation>
     */
    private function parseRecommendation(string $content, array $candidates): array
    {
        $payload = trim($content);
        $payload = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $payload) ?? $payload;

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Play-next suggester returned invalid JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Play-next suggester returned an unexpected payload.');
        }

        $recommendations = $decoded['recommendations'] ?? null;

        if (!is_array($recommendations) || 2 !== count($recommendations)) {
            throw new \RuntimeException('Play-next suggester did not return exactly two recommendations.');
        }

        $byId = [];
        foreach ($candidates as $candidate) {
            $byId[$candidate->getId()] = $candidate;
        }

        $expectedSlots = ['continue', 'alternate'];
        $usedGameIds = [];
        $parsedRecommendations = [];

        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) {
                throw new \RuntimeException('Play-next suggester returned malformed recommendation data.');
            }

            $slot = isset($recommendation['slot']) && is_string($recommendation['slot']) ? trim($recommendation['slot']) : '';
            $gameId = isset($recommendation['gameId']) && is_string($recommendation['gameId']) ? trim($recommendation['gameId']) : '';
            $reason = isset($recommendation['reason']) && is_string($recommendation['reason']) ? trim($recommendation['reason']) : '';

            if (!in_array($slot, $expectedSlots, true) || '' === $gameId || '' === $reason) {
                throw new \RuntimeException('Play-next suggester returned incomplete recommendation data.');
            }

            if (!isset($byId[$gameId])) {
                throw new \RuntimeException('Play-next suggester selected an unknown game candidate.');
            }

            if (in_array($gameId, $usedGameIds, true)) {
                throw new \RuntimeException('Play-next suggester repeated the same game twice.');
            }

            $usedGameIds[] = $gameId;
            $parsedRecommendations[$slot] = new PlayNextRecommendation(
                $slot,
                $byId[$gameId]->getId(),
                $byId[$gameId]->getTitle(),
                $reason,
            );
        }

        foreach ($expectedSlots as $slot) {
            if (!isset($parsedRecommendations[$slot])) {
                throw new \RuntimeException(sprintf('Play-next suggester did not return the "%s" recommendation.', $slot));
            }
        }

        return [
            $parsedRecommendations['continue'],
            $parsedRecommendations['alternate'],
        ];
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length
            ? rtrim(mb_substr($value, 0, $length - 1)).'…'
            : $value;
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
