<?php

namespace App\Service;

use App\Entity\EarnedTrophy;
use App\Entity\Game;
use App\Entity\Journey;
use App\Entity\LogEntry;
use App\Entity\User;
use App\Repository\EarnedTrophyRepository;
use App\Repository\GameRepository;
use App\Repository\JourneyRepository;
use App\Repository\LogEntryRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class SyncService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRepository $gameRepository,
        private readonly JourneyRepository $journeyRepository,
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
        $cursor = $this->cursor($payload['cursor'] ?? null);
        $full = true === ($payload['full'] ?? false);
        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];

        return $this->entityManager->wrapInTransaction(function () use ($user, $cursor, $full, $changes): array {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);

            $acknowledged = [
                'games' => [],
                'journeys' => [],
                'logs' => [],
                'earnedTrophies' => [],
            ];

            foreach ($this->records($changes, 'games') as $data) {
                $acknowledged['games'][] = $this->mergeGame($user, $data)->getId();
            }
            $this->entityManager->flush();

            foreach ($this->records($changes, 'journeys') as $data) {
                $acknowledged['journeys'][] = $this->mergeJourney($user, $data)->getId();
            }
            $this->entityManager->flush();

            foreach ($this->records($changes, 'logs') as $data) {
                $acknowledged['logs'][] = $this->mergeLogEntry($user, $data)->getId();
            }

            foreach ($this->records($changes, 'earnedTrophies') as $data) {
                $trophy = $this->mergeEarnedTrophy($user, $data);
                $acknowledged['earnedTrophies'][] = $this->clientTrophyId($trophy);
            }
            $this->entityManager->flush();

            $responseChanges = [
                'games' => $this->responseEntities(
                    $full
                        ? $this->gameRepository->findAllForUser($user)
                        : $this->gameRepository->findChangedForUser($user, $cursor),
                    $acknowledged['games'],
                    fn (string $id): ?Game => $this->gameRepository->findOneForUserById($user, $id),
                    fn (Game $game): array => $this->serializeGame($game),
                ),
                'journeys' => $this->responseEntities(
                    $full
                        ? $this->journeyRepository->findAllForUser($user)
                        : $this->journeyRepository->findChangedForUser($user, $cursor),
                    $acknowledged['journeys'],
                    fn (string $id): ?Journey => $this->journeyRepository->findOneForUserById($user, $id),
                    fn (Journey $journey): array => $this->serializeJourney($journey),
                ),
                'logs' => $this->responseEntities(
                    $full
                        ? $this->logEntryRepository->findAllForUser($user)
                        : $this->logEntryRepository->findChangedForUser($user, $cursor),
                    $acknowledged['logs'],
                    fn (string $id): ?LogEntry => $this->logEntryRepository->findOneForUserById($user, $id),
                    fn (LogEntry $log): array => $this->serializeLogEntry($log),
                ),
                'earnedTrophies' => $this->responseEntities(
                    $full
                        ? $this->earnedTrophyRepository->findAllForUser($user)
                        : $this->earnedTrophyRepository->findChangedForUser($user, $cursor),
                    $acknowledged['earnedTrophies'],
                    fn (string $id): ?EarnedTrophy => $this->earnedTrophyRepository->findOneForUserById($user, $id),
                    fn (EarnedTrophy $trophy): array => $this->serializeEarnedTrophy($trophy),
                    fn (EarnedTrophy $trophy): string => $this->clientTrophyId($trophy),
                ),
            ];

            return [
                'cursor' => $user->getSyncRevision(),
                'acknowledged' => $acknowledged,
                'changes' => $responseChanges,
                'totals' => [
                    'games' => count($this->gameRepository->findAllForUser($user)),
                    'journeys' => count($this->journeyRepository->findAllForUser($user)),
                    'logs' => count($this->logEntryRepository->findAllForUser($user)),
                ],
                'syncedAt' => $this->formatDateTime(new \DateTimeImmutable()),
            ];
        });
    }

    /** @param array<string, mixed> $data */
    private function mergeGame(User $user, array $data): Game
    {
        $id = $this->requireString($data, 'id');
        $updatedAt = $this->requireDateTime($data, 'updatedAt');
        $game = $this->gameRepository->findOneForUserById($user, $id);

        if ($game instanceof Game && $updatedAt < $game->getUpdatedAt()) {
            return $game;
        }

        if (!$game instanceof Game) {
            $game = (new Game())->setId($id)->setUser($user);
            $this->entityManager->persist($game);
        }

        return $game
            ->setTitle($this->string($data['title'] ?? null))
            ->setReleaseYear($this->integerOrNull($data['releaseYear'] ?? null))
            ->setDevelopers($this->strings($data['developers'] ?? []))
            ->setPublishers($this->strings($data['publishers'] ?? []))
            ->setGenres($this->strings($data['genres'] ?? []))
            ->setThemes($this->strings($data['themes'] ?? []))
            ->setGameModes($this->strings($data['gameModes'] ?? []))
            ->setTags($this->strings($data['tags'] ?? []))
            ->setCover($this->recordOrNull($data['cover'] ?? null))
            ->setExternalReferences($this->recordsValue($data['externalReferences'] ?? []))
            ->setPlaytimeEstimates($this->recordOrNull($data['playtimeEstimates'] ?? null))
            ->setMetadataReviewedAt($this->dateTimeOrNull($data['metadataReviewedAt'] ?? null))
            ->setCreatedAt($this->requireDateTime($data, 'createdAt'))
            ->setUpdatedAt($updatedAt)
            ->setDeletedAt($this->dateTimeOrNull($data['deletedAt'] ?? null))
            ->setRevision($user->nextSyncRevision());
    }

    /** @param array<string, mixed> $data */
    private function mergeJourney(User $user, array $data): Journey
    {
        $id = $this->requireString($data, 'id');
        $gameId = $this->requireString($data, 'gameId');
        $updatedAt = $this->requireDateTime($data, 'updatedAt');
        $game = $this->gameRepository->findOneForUserById($user, $gameId);

        if (!$game instanceof Game) {
            throw new \InvalidArgumentException(sprintf('Unknown gameId "%s" in journey sync payload.', $gameId));
        }

        $journey = $this->journeyRepository->findOneForUserById($user, $id);
        if ($journey instanceof Journey && $updatedAt < $journey->getUpdatedAt()) {
            return $journey;
        }

        if (!$journey instanceof Journey) {
            $journey = (new Journey())->setId($id);
            $this->entityManager->persist($journey);
        }

        return $journey
            ->setGame($game)
            ->setStatus($this->string($data['status'] ?? null, 'backlog'))
            ->setPlatform($this->string($data['platform'] ?? null))
            ->setOwnershipType($this->nullableString($data['ownershipType'] ?? null))
            ->setPriority($this->nullableString($data['priority'] ?? null))
            ->setRating($this->integerOrNull($data['rating'] ?? null))
            ->setReview($this->string($data['review'] ?? null))
            ->setPlayTimeHours($this->decimalOrNull($data['playTimeHours'] ?? null))
            ->setStartedAt($this->dateOrNull($data['startedAt'] ?? null))
            ->setFinishedAt($this->dateOrNull($data['finishedAt'] ?? null))
            ->setPausedAt($this->dateOrNull($data['pausedAt'] ?? null))
            ->setNudgeAt($this->dateOrNull($data['nudgeAt'] ?? null))
            ->setCreatedAt($this->requireDateTime($data, 'createdAt'))
            ->setUpdatedAt($updatedAt)
            ->setDeletedAt($this->dateTimeOrNull($data['deletedAt'] ?? null))
            ->setRevision($user->nextSyncRevision());
    }

    /** @param array<string, mixed> $data */
    private function mergeLogEntry(User $user, array $data): LogEntry
    {
        $id = $this->requireString($data, 'id');
        $journeyId = $this->requireString($data, 'journeyId');
        $updatedAt = $this->requireDateTime($data, 'updatedAt');
        $journey = $this->journeyRepository->findOneForUserById($user, $journeyId);

        if (!$journey instanceof Journey) {
            throw new \InvalidArgumentException(sprintf('Unknown journeyId "%s" in log sync payload.', $journeyId));
        }

        $log = $this->logEntryRepository->findOneForUserById($user, $id);
        if ($log instanceof LogEntry && $updatedAt < $log->getUpdatedAt()) {
            return $log;
        }

        if (!$log instanceof LogEntry) {
            $log = (new LogEntry())->setId($id);
            $this->entityManager->persist($log);
        }

        return $log
            ->setJourney($journey)
            ->setContent($this->string($data['content'] ?? null))
            ->setCreatedAt($this->requireDateTime($data, 'createdAt'))
            ->setUpdatedAt($updatedAt)
            ->setDeletedAt($this->dateTimeOrNull($data['deletedAt'] ?? null))
            ->setRevision($user->nextSyncRevision());
    }

    /** @param array<string, mixed> $data */
    private function mergeEarnedTrophy(User $user, array $data): EarnedTrophy
    {
        $id = $this->requireString($data, 'id');
        $updatedAt = $this->requireDateTime($data, 'updatedAt');
        $trophy = $this->earnedTrophyRepository->findOneForUserById($user, $id);

        if ($trophy instanceof EarnedTrophy && $updatedAt < $trophy->getUpdatedAt()) {
            return $trophy;
        }

        if (!$trophy instanceof EarnedTrophy) {
            $trophy = (new EarnedTrophy())->setId($this->trophyStorageId($user, $id))->setUser($user);
            $this->entityManager->persist($trophy);
        }

        return $trophy
            ->setTrophyId($this->requireString($data, 'trophyId'))
            ->setEarnedAt($this->requireDateTime($data, 'earnedAt'))
            ->setGameId($this->nullableString($data['gameId'] ?? null))
            ->setContext($this->recordOrNull($data['context'] ?? null))
            ->setCreatedAt($this->requireDateTime($data, 'createdAt'))
            ->setUpdatedAt($updatedAt)
            ->setDeletedAt($this->dateTimeOrNull($data['deletedAt'] ?? null))
            ->setRevision($user->nextSyncRevision());
    }

    private function serializeGame(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'title' => $game->getTitle(),
            'releaseYear' => $game->getReleaseYear(),
            'developers' => $game->getDevelopers(),
            'publishers' => $game->getPublishers(),
            'genres' => $game->getGenres(),
            'themes' => $game->getThemes(),
            'gameModes' => $game->getGameModes(),
            'tags' => $game->getTags(),
            'cover' => $game->getCover(),
            'externalReferences' => $game->getExternalReferences(),
            'playtimeEstimates' => $game->getPlaytimeEstimates(),
            'metadataReviewedAt' => $this->formatNullableDateTime($game->getMetadataReviewedAt()),
            'createdAt' => $this->formatDateTime($game->getCreatedAt()),
            'updatedAt' => $this->formatDateTime($game->getUpdatedAt()),
            'deletedAt' => $this->formatNullableDateTime($game->getDeletedAt()),
        ];
    }

    private function serializeJourney(Journey $journey): array
    {
        return [
            'id' => $journey->getId(),
            'gameId' => $journey->getGame()?->getId(),
            'status' => $journey->getStatus(),
            'platform' => $journey->getPlatform(),
            'ownershipType' => $journey->getOwnershipType(),
            'priority' => $journey->getPriority(),
            'rating' => $journey->getRating(),
            'review' => $journey->getReview(),
            'playTimeHours' => null !== $journey->getPlayTimeHours() ? (float) $journey->getPlayTimeHours() : null,
            'startedAt' => $this->formatDate($journey->getStartedAt()),
            'finishedAt' => $this->formatDate($journey->getFinishedAt()),
            'pausedAt' => $this->formatDate($journey->getPausedAt()),
            'nudgeAt' => $this->formatDate($journey->getNudgeAt()),
            'createdAt' => $this->formatDateTime($journey->getCreatedAt()),
            'updatedAt' => $this->formatDateTime($journey->getUpdatedAt()),
            'deletedAt' => $this->formatNullableDateTime($journey->getDeletedAt()),
        ];
    }

    private function serializeLogEntry(LogEntry $log): array
    {
        return [
            'id' => $log->getId(),
            'journeyId' => $log->getJourney()?->getId(),
            'content' => $log->getContent(),
            'createdAt' => $this->formatDateTime($log->getCreatedAt()),
            'updatedAt' => $this->formatDateTime($log->getUpdatedAt()),
            'deletedAt' => $this->formatNullableDateTime($log->getDeletedAt()),
        ];
    }

    private function serializeEarnedTrophy(EarnedTrophy $trophy): array
    {
        return [
            'id' => $this->clientTrophyId($trophy),
            'trophyId' => $trophy->getTrophyId(),
            'earnedAt' => $this->formatDateTime($trophy->getEarnedAt()),
            'gameId' => $trophy->getGameId(),
            'context' => $trophy->getContext(),
            'createdAt' => $this->formatDateTime($trophy->getCreatedAt()),
            'updatedAt' => $this->formatDateTime($trophy->getUpdatedAt()),
            'deletedAt' => $this->formatNullableDateTime($trophy->getDeletedAt()),
        ];
    }

    /**
     * @template T of object
     * @param list<T> $changed
     * @param list<string> $submittedIds
     * @param callable(string): ?T $find
     * @param callable(T): array<string, mixed> $serialize
     * @param (callable(T): string)|null $id
     * @return list<array<string, mixed>>
     */
    private function responseEntities(
        array $changed,
        array $submittedIds,
        callable $find,
        callable $serialize,
        ?callable $id = null,
    ): array {
        $id ??= static fn (object $entity): string => $entity->getId();
        $entities = [];
        foreach ($changed as $entity) {
            $entities[$id($entity)] = $entity;
        }
        foreach ($submittedIds as $submittedId) {
            $entity = $find($submittedId);
            if (null !== $entity) {
                $entities[$submittedId] = $entity;
            }
        }

        return array_values(array_map($serialize, $entities));
    }

    /** @return list<array<string, mixed>> */
    private function records(array $changes, string $key): array
    {
        $records = $changes[$key] ?? [];

        return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
    }

    private function cursor(mixed $value): int
    {
        return is_int($value) && $value >= 0 ? $value : 0;
    }

    private function trophyStorageId(User $user, string $id): string
    {
        return sprintf('%d:%s', $user->getId() ?? 0, $id);
    }

    private function clientTrophyId(EarnedTrophy $trophy): string
    {
        $prefix = sprintf('%d:', $trophy->getUser()?->getId() ?? 0);

        return str_starts_with($trophy->getId(), $prefix)
            ? substr($trophy->getId(), strlen($prefix))
            : $trophy->getId();
    }

    private function requireString(array $data, string $key): string
    {
        $value = $this->nullableString($data[$key] ?? null);
        if (null === $value) {
            throw new \InvalidArgumentException(sprintf('Missing or invalid "%s" field.', $key));
        }

        return $value;
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_string($value) ? trim($value) : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->string($value);

        return '' !== $value ? $value : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_int($value) || is_numeric($value) ? (int) $value : null;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        return is_numeric($value) ? number_format((float) $value, 1, '.', '') : null;
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_unique(array_filter(array_map(
                fn (mixed $item): string => $this->string($item),
                $value,
            ))))
            : [];
    }

    /** @return array<string, mixed>|null */
    private function recordOrNull(mixed $value): ?array
    {
        return is_array($value) && !array_is_list($value) ? $value : null;
    }

    /** @return list<array<string, mixed>> */
    private function recordsValue(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, fn (mixed $item): bool => null !== $this->recordOrNull($item)))
            : [];
    }

    private function requireDateTime(array $data, string $key): \DateTimeImmutable
    {
        $value = $this->dateTimeOrNull($data[$key] ?? null);
        if (!$value instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException(sprintf('Missing or invalid "%s" field.', $key));
        }

        return $value;
    }

    private function dateTimeOrNull(mixed $value): ?\DateTimeImmutable
    {
        try {
            return is_string($value) && '' !== $value ? new \DateTimeImmutable($value) : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function dateOrNull(mixed $value): ?\DateTimeImmutable
    {
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    private function formatDateTime(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function formatNullableDateTime(?\DateTimeImmutable $value): ?string
    {
        return null !== $value ? $this->formatDateTime($value) : null;
    }

    private function formatDate(?\DateTimeImmutable $value): ?string
    {
        return $value?->format('Y-m-d');
    }
}
