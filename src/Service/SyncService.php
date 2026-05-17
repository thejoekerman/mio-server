<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\EarnedTrophy;
use App\Entity\LogEntry;
use App\Entity\User;
use App\Repository\EarnedTrophyRepository;
use App\Repository\GameRepository;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

class SyncService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRepository $gameRepository,
        private readonly LogEntryRepository $logEntryRepository,
        private readonly EarnedTrophyRepository $earnedTrophyRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function sync(User $user, array $payload): array
    {
        $gamesPayload = is_array($payload['games'] ?? null) ? $payload['games'] : [];
        $logsPayload = is_array($payload['logs'] ?? null) ? $payload['logs'] : [];
        $earnedTrophiesPayload = is_array($payload['earnedTrophies'] ?? null) ? $payload['earnedTrophies'] : [];

        foreach ($gamesPayload as $gameData) {
            if (!is_array($gameData)) {
                continue;
            }

            $this->mergeGame($user, $gameData);
        }

        $this->entityManager->flush();

        foreach ($logsPayload as $logData) {
            if (!is_array($logData)) {
                continue;
            }

            $this->mergeLogEntry($user, $logData);
        }

        $this->entityManager->flush();

        foreach ($earnedTrophiesPayload as $earnedTrophyData) {
            if (!is_array($earnedTrophyData)) {
                continue;
            }

            $this->mergeEarnedTrophy($user, $earnedTrophyData);
        }

        $this->entityManager->flush();

        return [
            'games' => array_map(
                fn (Game $game): array => $this->serializeGame($game),
                $this->gameRepository->findAllForUser($user),
            ),
            'logs' => array_map(
                fn (LogEntry $logEntry): array => $this->serializeLogEntry($logEntry),
                $this->logEntryRepository->findAllForUser($user),
            ),
            'earnedTrophies' => array_map(
                fn (EarnedTrophy $earnedTrophy): array => $this->serializeEarnedTrophy($earnedTrophy),
                $this->earnedTrophyRepository->findAllForUser($user),
            ),
            'syncedAt' => $this->formatDateTime(new \DateTimeImmutable()),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mergeGame(User $user, array $data): void
    {
        $id = $this->requireString($data, 'id');
        $incomingUpdatedAt = $this->requireDateTime($data, 'updatedAt');

        $game = $this->gameRepository->findOneForUserById($user, $id);

        $isNewGame = !$game instanceof Game;

        if ($isNewGame) {
            $game = (new Game())
                ->setId($id)
                ->setUser($user);

            $this->entityManager->persist($game);
        } elseif ($incomingUpdatedAt < $game->getUpdatedAt()) {
            return;
        }

        $previousIgdbId = $game->getIgdbId();
        $hasIncomingIgdbId = array_key_exists('igdbId', $data);
        $incomingIgdbId = $hasIncomingIgdbId
            ? $this->positiveIntOrNull($data['igdbId'])
            : $previousIgdbId;
        $acceptIncomingIgdbMetadata = $hasIncomingIgdbId
            && null !== $incomingIgdbId
            && ($isNewGame || $previousIgdbId === $incomingIgdbId);

        $game
            ->setTitle($this->stringOrDefault($data['title'] ?? null))
            ->setStatus($this->stringOrDefault($data['status'] ?? null, 'backlog'))
            ->setRating($this->intOrNull($data['rating'] ?? null))
            ->setPlayTimeHours($this->decimalStringOrNull($data['playTimeHours'] ?? null))
            ->setReview($this->stringOrDefault($data['review'] ?? null))
            ->setPlatform($this->stringOrDefault($data['platform'] ?? null))
            ->setTags($this->stringArray($data['tags'] ?? []))
            ->setIgdbId($incomingIgdbId)
            ->setFinishedAt($this->dateOnlyOrNull($data['finishedAt'] ?? null, 'finishedAt'))
            ->setCreatedAt($this->requireDateTime($data, 'createdAt'))
            ->setUpdatedAt($incomingUpdatedAt)
            ->setDeletedAt($this->dateTimeOrNull($data['deletedAt'] ?? null));

        if (array_key_exists('ownershipType', $data)) {
            $game->setOwnershipType($this->ownershipTypeOrNull($data['ownershipType']));
        }

        if (array_key_exists('pausedAt', $data)) {
            $game->setPausedAt($this->dateOnlyOrNull($data['pausedAt'], 'pausedAt'));
        }

        if (array_key_exists('nudgeAt', $data)) {
            $game->setNudgeAt($this->dateOnlyOrNull($data['nudgeAt'], 'nudgeAt'));
        }

        if ($hasIncomingIgdbId && $previousIgdbId !== $incomingIgdbId) {
            $game
                ->setIgdbUrl(null)
                ->setCoverUrl(null)
                ->setIgdbTtbHastilySeconds(null)
                ->setIgdbTtbNormallySeconds(null)
                ->setIgdbTtbCompletelySeconds(null)
                ->setIgdbTtbCount(null)
                ->setIgdbTtbUpdatedAt(null)
                ->setIgdbDevelopers(null)
                ->setIgdbPublishers(null)
                ->setIgdbThemes(null)
                ->setIgdbGameModes(null);
        }

        if ($acceptIncomingIgdbMetadata) {
            $this->mergeIgdbMetadata($game, $data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mergeIgdbMetadata(Game $game, array $data): void
    {
        if (array_key_exists('igdbUrl', $data)) {
            $igdbUrl = $this->stringOrNull($data['igdbUrl']);

            if (null !== $igdbUrl || null === $game->getIgdbUrl()) {
                $game->setIgdbUrl($igdbUrl);
            }
        }

        if (array_key_exists('coverUrl', $data)) {
            $coverUrl = $this->stringOrNull($data['coverUrl']);

            if (null !== $coverUrl || null === $game->getCoverUrl()) {
                $game->setCoverUrl($coverUrl);
            }
        }

        if (array_key_exists('igdbTtbHastilySeconds', $data)) {
            $hastilySeconds = $this->positiveIntOrNull($data['igdbTtbHastilySeconds']);

            if (null !== $hastilySeconds || null === $game->getIgdbTtbHastilySeconds()) {
                $game->setIgdbTtbHastilySeconds($hastilySeconds);
            }
        }

        if (array_key_exists('igdbTtbNormallySeconds', $data)) {
            $normallySeconds = $this->positiveIntOrNull($data['igdbTtbNormallySeconds']);

            if (null !== $normallySeconds || null === $game->getIgdbTtbNormallySeconds()) {
                $game->setIgdbTtbNormallySeconds($normallySeconds);
            }
        }

        if (array_key_exists('igdbTtbCompletelySeconds', $data)) {
            $completelySeconds = $this->positiveIntOrNull($data['igdbTtbCompletelySeconds']);

            if (null !== $completelySeconds || null === $game->getIgdbTtbCompletelySeconds()) {
                $game->setIgdbTtbCompletelySeconds($completelySeconds);
            }
        }

        if (array_key_exists('igdbTtbCount', $data)) {
            $count = $this->nonNegativeIntOrNull($data['igdbTtbCount']);

            if (null !== $count || null === $game->getIgdbTtbCount()) {
                $game->setIgdbTtbCount($count);
            }
        }

        if (array_key_exists('igdbTtbUpdatedAt', $data)) {
            $updatedAt = $this->dateTimeOrNull($data['igdbTtbUpdatedAt']);

            if (null !== $updatedAt || null === $game->getIgdbTtbUpdatedAt()) {
                $game->setIgdbTtbUpdatedAt($updatedAt);
            }
        }

        if (array_key_exists('igdbDevelopers', $data)) {
            $developers = $this->stringArrayOrNull($data['igdbDevelopers']);

            if (null !== $developers && ([] !== $developers || null === $game->getIgdbDevelopers())) {
                $game->setIgdbDevelopers($developers);
            }
        }

        if (array_key_exists('igdbPublishers', $data)) {
            $publishers = $this->stringArrayOrNull($data['igdbPublishers']);

            if (null !== $publishers && ([] !== $publishers || null === $game->getIgdbPublishers())) {
                $game->setIgdbPublishers($publishers);
            }
        }

        if (array_key_exists('igdbThemes', $data)) {
            $themes = $this->stringArrayOrNull($data['igdbThemes']);

            if (null !== $themes && ([] !== $themes || null === $game->getIgdbThemes())) {
                $game->setIgdbThemes($themes);
            }
        }

        if (array_key_exists('igdbGameModes', $data)) {
            $gameModes = $this->stringArrayOrNull($data['igdbGameModes']);

            if (null !== $gameModes && ([] !== $gameModes || null === $game->getIgdbGameModes())) {
                $game->setIgdbGameModes($gameModes);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mergeLogEntry(User $user, array $data): void
    {
        $id = $this->requireString($data, 'id');
        $gameId = $this->requireString($data, 'gameId');
        $incomingUpdatedAt = $this->requireDateTime($data, 'updatedAt');

        $game = $this->gameRepository->findOneForUserById($user, $gameId);

        if (!$game instanceof Game) {
            throw new \InvalidArgumentException(sprintf('Unknown gameId "%s" in log sync payload.', $gameId));
        }

        $logEntry = $this->logEntryRepository->findOneForUserById($user, $id);

        if (!$logEntry instanceof LogEntry) {
            $logEntry = (new LogEntry())
                ->setId($id)
                ->setGame($game);

            $this->entityManager->persist($logEntry);
        } elseif ($incomingUpdatedAt < $logEntry->getUpdatedAt()) {
            return;
        }

        $logEntry
            ->setGame($game)
            ->setContent($this->stringOrDefault($data['content'] ?? null))
            ->setCreatedAt($this->requireDateTime($data, 'createdAt'))
            ->setUpdatedAt($incomingUpdatedAt)
            ->setDeletedAt($this->dateTimeOrNull($data['deletedAt'] ?? null));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mergeEarnedTrophy(User $user, array $data): void
    {
        $id = $this->requireString($data, 'id');
        $incomingUpdatedAt = $this->requireDateTime($data, 'updatedAt');

        $earnedTrophy = $this->earnedTrophyRepository->findOneForUserById($user, $id);

        if (!$earnedTrophy instanceof EarnedTrophy) {
            $earnedTrophy = (new EarnedTrophy())
                ->setId($this->storageIdForUser($user, $id))
                ->setUser($user);

            $this->entityManager->persist($earnedTrophy);
        } elseif ($incomingUpdatedAt < $earnedTrophy->getUpdatedAt()) {
            return;
        }

        $earnedTrophy
            ->setTrophyId($this->requireString($data, 'trophyId'))
            ->setEarnedAt($this->requireDateTime($data, 'earnedAt'))
            ->setGameId($this->stringOrNull($data['gameId'] ?? null))
            ->setContext($this->recordOrNull($data['context'] ?? null))
            ->setCreatedAt($this->requireDateTime($data, 'createdAt'))
            ->setUpdatedAt($incomingUpdatedAt)
            ->setDeletedAt($this->dateTimeOrNull($data['deletedAt'] ?? null));
    }

    private function serializeGame(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'title' => $game->getTitle(),
            'status' => $game->getStatus(),
            'rating' => $game->getRating(),
            'playTimeHours' => null !== $game->getPlayTimeHours() ? (float) $game->getPlayTimeHours() : null,
            'review' => $game->getReview(),
            'platform' => $game->getPlatform(),
            'ownershipType' => $game->getOwnershipType(),
            'tags' => $game->getTags(),
            'igdbId' => $game->getIgdbId(),
            'igdbUrl' => $game->getIgdbUrl(),
            'coverUrl' => $game->getCoverUrl(),
            'igdbTtbHastilySeconds' => $game->getIgdbTtbHastilySeconds(),
            'igdbTtbNormallySeconds' => $game->getIgdbTtbNormallySeconds(),
            'igdbTtbCompletelySeconds' => $game->getIgdbTtbCompletelySeconds(),
            'igdbTtbCount' => $game->getIgdbTtbCount(),
            'igdbTtbUpdatedAt' => null !== $game->getIgdbTtbUpdatedAt()
                ? $this->formatDateTime($game->getIgdbTtbUpdatedAt())
                : null,
            'igdbDevelopers' => $game->getIgdbDevelopers(),
            'igdbPublishers' => $game->getIgdbPublishers(),
            'igdbThemes' => $game->getIgdbThemes(),
            'igdbGameModes' => $game->getIgdbGameModes(),
            'finishedAt' => $game->getFinishedAt()?->format('Y-m-d'),
            'pausedAt' => $game->getPausedAt()?->format('Y-m-d'),
            'nudgeAt' => $game->getNudgeAt()?->format('Y-m-d'),
            'createdAt' => $this->formatDateTime($game->getCreatedAt()),
            'updatedAt' => $this->formatDateTime($game->getUpdatedAt()),
            'deletedAt' => null !== $game->getDeletedAt() ? $this->formatDateTime($game->getDeletedAt()) : null,
        ];
    }

    private function serializeLogEntry(LogEntry $logEntry): array
    {
        return [
            'id' => $logEntry->getId(),
            'gameId' => $logEntry->getGame()?->getId(),
            'content' => $logEntry->getContent(),
            'createdAt' => $this->formatDateTime($logEntry->getCreatedAt()),
            'updatedAt' => $this->formatDateTime($logEntry->getUpdatedAt()),
            'deletedAt' => null !== $logEntry->getDeletedAt() ? $this->formatDateTime($logEntry->getDeletedAt()) : null,
        ];
    }

    private function serializeEarnedTrophy(EarnedTrophy $earnedTrophy): array
    {
        return [
            'id' => $this->clientIdForUser($earnedTrophy->getUser(), $earnedTrophy->getId()),
            'trophyId' => $earnedTrophy->getTrophyId(),
            'earnedAt' => $this->formatDateTime($earnedTrophy->getEarnedAt()),
            'gameId' => $earnedTrophy->getGameId(),
            'context' => $earnedTrophy->getContext(),
            'createdAt' => $this->formatDateTime($earnedTrophy->getCreatedAt()),
            'updatedAt' => $this->formatDateTime($earnedTrophy->getUpdatedAt()),
            'deletedAt' => null !== $earnedTrophy->getDeletedAt() ? $this->formatDateTime($earnedTrophy->getDeletedAt()) : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value) || '' === trim($value)) {
            throw new \InvalidArgumentException(sprintf('Missing or invalid "%s" field.', $field));
        }

        return trim($value);
    }

    private function storageIdForUser(User $user, string $clientId): string
    {
        if (null === $user->getId()) {
            return $clientId;
        }

        return sprintf('%d:%s', $user->getId(), $clientId);
    }

    private function clientIdForUser(?User $user, string $storageId): string
    {
        if (null === $user?->getId()) {
            return $storageId;
        }

        $prefix = sprintf('%d:', $user->getId());

        if (!str_starts_with($storageId, $prefix)) {
            return $storageId;
        }

        return substr($storageId, strlen($prefix));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireDateTime(array $data, string $field): \DateTimeImmutable
    {
        $value = $data[$field] ?? null;

        $dateTime = $this->dateTimeOrNull($value);

        if (!$dateTime instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException(sprintf('Missing or invalid "%s" field.', $field));
        }

        return $dateTime;
    }

    private function stringOrDefault(mixed $value, string $default = ''): string
    {
        return is_string($value) ? trim($value) : $default;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        $integer = $this->intOrNull($value);

        return null !== $integer && $integer > 0 ? $integer : null;
    }

    private function nonNegativeIntOrNull(mixed $value): ?int
    {
        $integer = $this->intOrNull($value);

        return null !== $integer && $integer >= 0 ? $integer : null;
    }

    private function decimalStringOrNull(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 1, '.', '');
    }

    private function ownershipTypeOrNull(mixed $value): ?string
    {
        return match ($value) {
            'digital', 'physical', 'both' => $value,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function stringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);

            if ('' !== $trimmed) {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<string>|null
     */
    private function stringArrayOrNull(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }

        return $this->stringArray($value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recordOrNull(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return $value;
    }

    private function dateOnlyOrNull(mixed $value, string $field): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid "%s" field.', $field));
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if (!$date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException(sprintf('Invalid "%s" field.', $field));
        }

        return $date->setTime(0, 0);
    }

    private function dateTimeOrNull(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
