<?php

namespace App\Tests\Repository;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Tests\Api\ApiTestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class GameRepositoryTest extends ApiTestCase
{
    #[TestDox('findMissingIgdbMetadataForUser returns only the given user\'s games that still need metadata')]
    public function testReturnsOnlyTheUsersGamesNeedingMetadata(): void
    {
        $alice = $this->createUserWithSyncToken('alice@example.com', 'alice-token')['user'];
        $bob = $this->createUserWithSyncToken('bob@example.com', 'bob-token')['user'];

        $this->persistGame($alice, 'a-missing-1', igdbId: 1);
        $this->persistGame($alice, 'a-missing-2', igdbId: 2);
        $this->persistGame($alice, 'a-complete', igdbId: 3, complete: true);
        $this->persistGame($alice, 'a-deleted', igdbId: 4, deleted: true);
        $this->persistGame($alice, 'a-no-igdb-id', igdbId: null);
        $this->persistGame($bob, 'b-missing', igdbId: 9);
        $this->entityManager->flush();

        /** @var GameRepository $repository */
        $repository = $this->entityManager->getRepository(Game::class);

        $ids = array_map(
            static fn (Game $game): string => $game->getId(),
            $repository->findMissingIgdbMetadataForUser($alice, 100),
        );

        sort($ids);

        self::assertSame(['a-missing-1', 'a-missing-2'], $ids);
    }

    #[TestDox('findMissingIgdbMetadataForUser caps the result set at the given limit')]
    public function testRespectsTheLimit(): void
    {
        $alice = $this->createUserWithSyncToken('alice@example.com', 'alice-token')['user'];

        $this->persistGame($alice, 'a-missing-1', igdbId: 1);
        $this->persistGame($alice, 'a-missing-2', igdbId: 2);
        $this->persistGame($alice, 'a-missing-3', igdbId: 3);
        $this->entityManager->flush();

        /** @var GameRepository $repository */
        $repository = $this->entityManager->getRepository(Game::class);

        self::assertCount(2, $repository->findMissingIgdbMetadataForUser($alice, 2));
    }

    private function persistGame(
        User $user,
        string $id,
        ?int $igdbId,
        bool $complete = false,
        bool $deleted = false,
    ): void {
        $game = (new Game())
            ->setId($id)
            ->setUser($user)
            ->setTitle('Game '.$id)
            ->setIgdbId($igdbId);

        if ($complete) {
            $game
                ->setCoverUrl('https://images.example/'.$id)
                ->setIgdbUrl('https://igdb.example/'.$id)
                ->setIgdbTtbCount(3)
                ->setIgdbDevelopers(['Dev'])
                ->setIgdbPublishers(['Pub'])
                ->setIgdbThemes(['Theme'])
                ->setIgdbGameModes(['Mode'])
                ->setReleaseYear(2020);
        }

        if ($deleted) {
            $game->setDeletedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($game);
    }
}
