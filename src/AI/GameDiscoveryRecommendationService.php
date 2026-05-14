<?php

namespace App\AI;

use App\Entity\Game;
use App\Entity\LogEntry;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class GameDiscoveryRecommendationService
{
    public function __construct(
        #[Target('game_discovery_suggester')]
        private AgentInterface $gameDiscoverySuggester,
        #[Target('game_discovery_suggester_gemini')]
        private AgentInterface $gameDiscoverySuggesterGemini,
        private string $appEnv,
        private ?string $configuredProvider = null,
    ) {
    }

    /**
     * @param list<Game> $libraryGames
     * @return list<GameDiscoveryRecommendation>
     */
    public function recommend(array $libraryGames, ?string $provider = null, string $language = 'en'): array
    {
        $backlogPlatforms = $this->collectBacklogPlatforms($libraryGames);

        if ([] === $backlogPlatforms) {
            throw new \RuntimeException('No backlog platforms are available for discovery.');
        }

        $agent = match ($provider ?? $this->resolveProvider()) {
            'lmstudio' => $this->gameDiscoverySuggester,
            'gemini' => $this->gameDiscoverySuggesterGemini,
            default => throw new \InvalidArgumentException('Unsupported game-discovery provider.'),
        };

        $result = $agent->call(new MessageBag(Message::ofUser($this->buildPromptInput($libraryGames, $backlogPlatforms, $language))));

        if (!$result instanceof TextResult) {
            throw new \RuntimeException('Game-discovery suggester did not return text output.');
        }

        return $this->parseRecommendations($result->getContent(), $libraryGames, $backlogPlatforms);
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
     * @return array<string, string>
     */
    private function collectBacklogPlatforms(array $libraryGames): array
    {
        $platforms = [];

        foreach ($libraryGames as $game) {
            if (null !== $game->getDeletedAt() || 'backlog' !== $game->getStatus()) {
                continue;
            }

            $platform = trim($game->getPlatform());

            if ('' === $platform) {
                continue;
            }

            $platforms[mb_strtolower($platform)] = $platform;
        }

        ksort($platforms);

        return $platforms;
    }

    /**
     * @param list<Game>          $libraryGames
     * @param array<string,string> $backlogPlatforms
     */
    private function buildPromptInput(array $libraryGames, array $backlogPlatforms, string $language): string
    {
        $visibleGames = array_values(array_filter(
            $libraryGames,
            static fn (Game $game): bool => null === $game->getDeletedAt(),
        ));
        $finishedGames = array_values(array_filter(
            $visibleGames,
            static fn (Game $game): bool => 'finished' === $game->getStatus(),
        ));
        $backlogGames = array_values(array_filter(
            $visibleGames,
            static fn (Game $game): bool => 'backlog' === $game->getStatus(),
        ));

        usort(
            $finishedGames,
            static fn (Game $left, Game $right): int => ($right->getFinishedAt() ?? $right->getUpdatedAt()) <=> ($left->getFinishedAt() ?? $left->getUpdatedAt()),
        );

        $lines = [
            'Recommend new games that are not already in this MioLog library.',
            sprintf('Write the recommendation reason in %s.', $this->languageLabel($language)),
            '',
            'Allowed platforms, based only on current backlog games:',
            implode(', ', array_values($backlogPlatforms)),
            '',
            'Forbidden current backlog titles. Do not recommend these exact games:',
            [] !== $backlogGames ? implode('; ', array_map(static fn (Game $game): string => $game->getTitle(), $backlogGames)) : 'None.',
            '',
            'Current backlog taste signals, without titles:',
            $this->formatBacklogTasteSignals($backlogGames),
            '',
            'Recent finished games and taste signals:',
            [] !== $finishedGames
                ? implode("\n", array_map(fn (Game $game): string => $this->formatFinishedGame($game), array_slice($finishedGames, 0, 5)))
                : '- No finished games were provided.',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param list<Game> $backlogGames
     */
    private function formatBacklogTasteSignals(array $backlogGames): string
    {
        if ([] === $backlogGames) {
            return '- No backlog games were provided.';
        }

        return implode("\n", [
            sprintf('- tags: %s', $this->formatTopValues($backlogGames, static fn (Game $game): array => $game->getTags(), 12)),
            sprintf('- themes: %s', $this->formatTopValues($backlogGames, static fn (Game $game): array => $game->getIgdbThemes() ?? [], 10)),
            sprintf('- game modes: %s', $this->formatTopValues($backlogGames, static fn (Game $game): array => $game->getIgdbGameModes() ?? [], 8)),
        ]);
    }

    private function formatFinishedGame(Game $game): string
    {
        $lines = [
            sprintf('- %s', $game->getTitle()),
            sprintf('  platform: %s', $this->valueOrFallback($game->getPlatform(), 'unspecified')),
            sprintf('  tags: %s', [] !== $game->getTags() ? implode(', ', $game->getTags()) : 'none'),
            sprintf('  rating: %s', null !== $game->getRating() ? (string) $game->getRating() : 'none'),
        ];

        if (null !== $game->getIgdbDevelopers() && [] !== $game->getIgdbDevelopers()) {
            $lines[] = sprintf('  developers: %s', implode(', ', $game->getIgdbDevelopers()));
        }

        if (null !== $game->getIgdbThemes() && [] !== $game->getIgdbThemes()) {
            $lines[] = sprintf('  themes: %s', implode(', ', $game->getIgdbThemes()));
        }

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
     * @param list<Game>           $libraryGames
     * @param array<string,string> $backlogPlatforms
     * @return list<GameDiscoveryRecommendation>
     */
    private function parseRecommendations(string $content, array $libraryGames, array $backlogPlatforms): array
    {
        $payload = trim($content);
        $payload = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $payload) ?? $payload;

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Game-discovery suggester returned invalid JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Game-discovery suggester returned an unexpected payload.');
        }

        $recommendations = $decoded['recommendations'] ?? null;

        if (!is_array($recommendations) || [] === $recommendations) {
            throw new \RuntimeException('Game-discovery suggester did not return recommendations.');
        }

        $existingTitleKeys = [];
        foreach ($libraryGames as $game) {
            if (null === $game->getDeletedAt()) {
                $existingTitleKeys[] = $this->normalizeTitle($game->getTitle());
            }
        }

        $usedTitleKeys = [];
        $parsedRecommendations = [];

        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }

            $title = isset($recommendation['title']) && is_string($recommendation['title']) ? trim($recommendation['title']) : '';
            $reason = isset($recommendation['reason']) && is_string($recommendation['reason']) ? trim($recommendation['reason']) : '';
            $platforms = $recommendation['platforms'] ?? null;

            if ('' === $title || '' === $reason || !is_array($platforms)) {
                continue;
            }

            $titleKey = $this->normalizeTitle($title);

            if ('' === $titleKey || in_array($titleKey, $existingTitleKeys, true)) {
                continue;
            }

            if (in_array($titleKey, $usedTitleKeys, true)) {
                continue;
            }

            $matchedPlatforms = $this->normalizePlatforms($platforms, $backlogPlatforms);
            if ([] === $matchedPlatforms) {
                continue;
            }

            $usedTitleKeys[] = $titleKey;
            $parsedRecommendations[] = new GameDiscoveryRecommendation(
                $title,
                $matchedPlatforms,
                $reason,
                $this->normalizeIgdbUrl($recommendation['igdbUrl'] ?? null),
                $this->normalizeTtbNormallySeconds($recommendation['ttbNormallySeconds'] ?? null),
            );

            break;
        }

        if ([] === $parsedRecommendations) {
            throw new \RuntimeException('Game-discovery suggester did not return a usable new recommendation.');
        }

        return $parsedRecommendations;
    }

    /**
     * @param array<mixed>         $platforms
     * @param array<string,string> $backlogPlatforms
     * @return list<string>
     */
    private function normalizePlatforms(array $platforms, array $backlogPlatforms): array
    {
        $matchedPlatforms = [];

        foreach ($platforms as $platform) {
            if (!is_string($platform)) {
                continue;
            }

            $key = mb_strtolower(trim($platform));

            if (isset($backlogPlatforms[$key])) {
                $matchedPlatforms[$key] = $backlogPlatforms[$key];
            }
        }

        return array_values($matchedPlatforms);
    }

    private function normalizeIgdbUrl(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ('' === $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        $host = is_string($host) ? mb_strtolower($host) : '';

        if ('igdb.com' !== $host && !str_ends_with($host, '.igdb.com')) {
            return null;
        }

        return $url;
    }

    private function normalizeTtbNormallySeconds(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!is_int($value) || $value <= 0) {
            return null;
        }

        return $value;
    }

    private function normalizeTitle(string $title): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($title)) ?? '';
    }

    /**
     * @param list<Game> $games
     * @param callable(Game): list<string> $extractor
     */
    private function formatTopValues(array $games, callable $extractor, int $limit): string
    {
        $counts = [];
        $labels = [];

        foreach ($games as $game) {
            foreach ($extractor($game) as $value) {
                $value = trim($value);

                if ('' === $value) {
                    continue;
                }

                $key = mb_strtolower($value);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $labels[$key] = $value;
            }
        }

        if ([] === $counts) {
            return 'none';
        }

        arsort($counts);

        return implode(', ', array_map(
            static fn (string $key): string => $labels[$key],
            array_slice(array_keys($counts), 0, $limit),
        ));
    }

    private function valueOrFallback(string $value, string $fallback): string
    {
        $value = trim($value);

        return '' !== $value ? $value : $fallback;
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length
            ? rtrim(mb_substr($value, 0, $length - 1)).'…'
            : $value;
    }

    private function languageLabel(string $language): string
    {
        return 'de' === $language ? 'German' : 'English';
    }
}
