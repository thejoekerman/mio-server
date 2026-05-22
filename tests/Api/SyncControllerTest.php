<?php

namespace App\Tests\Api;

use App\Entity\Game;
use App\Entity\EarnedTrophy;
use App\Entity\LogEntry;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\TestDox;

final class SyncControllerTest extends ApiTestCase
{
    #[TestDox('The sync endpoint creates games and logs for the authenticated user')]
    public function testSyncEndpointCreatesGamesAndLogsForTheAuthenticatedUser(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Final Fantasy VII Remake',
                    'status' => 'paused',
                    'rating' => null,
                    'playTimeHours' => 14.5,
                    'review' => '',
                    'platform' => 'PS5',
                    'ownershipType' => 'physical',
                    'tags' => ['JRPG', 'Action'],
                    'releaseYear' => 2020,
                    'priority' => 'high-interest',
                    'developer' => 'Square Enix Creative Studio I',
                    'publisher' => 'Square Enix',
                    'finishedAt' => null,
                    'pausedAt' => '2026-04-23',
                    'nudgeAt' => '2026-05-07',
                    'createdAt' => '2026-04-23T10:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [
                [
                    'id' => 'log-1',
                    'gameId' => 'game-1',
                    'content' => 'Boss fight was dope!',
                    'createdAt' => '2026-04-23T10:30:00Z',
                    'updatedAt' => '2026-04-23T10:30:00Z',
                    'deletedAt' => null,
                ],
            ],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['games']);
        self::assertCount(1, $payload['logs']);
        self::assertSame('Final Fantasy VII Remake', $payload['games'][0]['title']);
        self::assertSame('Boss fight was dope!', $payload['logs'][0]['content']);
        self::assertSame(14.5, $payload['games'][0]['playTimeHours']);
        self::assertSame('physical', $payload['games'][0]['ownershipType']);
        self::assertSame(2020, $payload['games'][0]['releaseYear']);
        self::assertSame('high-interest', $payload['games'][0]['priority']);
        self::assertSame('Square Enix Creative Studio I', $payload['games'][0]['developer']);
        self::assertSame('Square Enix', $payload['games'][0]['publisher']);
        self::assertSame('2026-04-23', $payload['games'][0]['pausedAt']);
        self::assertSame('2026-05-07', $payload['games'][0]['nudgeAt']);
    }

    #[TestDox('The sync endpoint creates earned trophies for the authenticated user')]
    public function testSyncEndpointCreatesEarnedTrophiesForTheAuthenticatedUser(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/sync', [
            'games' => [],
            'logs' => [],
            'earnedTrophies' => [
                [
                    'id' => 'trophy-first-log',
                    'trophyId' => 'first-log',
                    'earnedAt' => '2026-04-23T10:30:00Z',
                    'gameId' => null,
                    'context' => ['count' => 1],
                    'createdAt' => '2026-04-23T10:30:00Z',
                    'updatedAt' => '2026-04-23T10:30:00Z',
                    'deletedAt' => null,
                ],
            ],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload['earnedTrophies']);
        self::assertSame('first-log', $payload['earnedTrophies'][0]['trophyId']);
        self::assertSame(['count' => 1], $payload['earnedTrophies'][0]['context']);

        /** @var EarnedTrophy $earnedTrophy */
        $earnedTrophy = $this->entityManager->getRepository(EarnedTrophy::class)->findOneBy([
            'user' => $auth['user'],
            'trophyId' => 'first-log',
        ]);

        self::assertSame('first-log', $earnedTrophy->getTrophyId());
    }

    #[TestDox('The sync endpoint allows different users to earn the same trophy')]
    public function testSyncEndpointAllowsDifferentUsersToEarnTheSameTrophy(): void
    {
        $firstAuth = $this->createUserWithSyncToken(
            email: 'first@example.com',
            plainToken: 'first-sync-token',
        );
        $secondAuth = $this->createUserWithSyncToken(
            email: 'second@example.com',
            plainToken: 'second-sync-token',
        );

        $payload = [
            'games' => [],
            'logs' => [],
            'earnedTrophies' => [
                [
                    'id' => 'trophy-first-log',
                    'trophyId' => 'first-log',
                    'earnedAt' => '2026-04-23T10:30:00Z',
                    'gameId' => null,
                    'context' => ['count' => 1],
                    'createdAt' => '2026-04-23T10:30:00Z',
                    'updatedAt' => '2026-04-23T10:30:00Z',
                    'deletedAt' => null,
                ],
            ],
        ];

        $this->postJson('/api/sync', $payload, $firstAuth['plainToken']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->postJson('/api/sync', $payload, $secondAuth['plainToken']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $responsePayload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('trophy-first-log', $responsePayload['earnedTrophies'][0]['id']);
        self::assertCount(2, $this->entityManager->getRepository(EarnedTrophy::class)->findAll());
    }

    #[TestDox('The sync endpoint keeps newer game data when an older payload arrives later')]
    public function testSyncEndpointKeepsNewerGameDataWhenAnOlderPayloadArrivesLater(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Clair Obscur')
            ->setStatus('playing')
            ->setReview('Keep this newer review')
            ->setPlatform('PS5')
            ->setTags(['RPG'])
            ->setCreatedAt(new DateTimeImmutable('2026-04-23T09:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-04-23T12:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Older title that should lose',
                    'status' => 'paused',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => 'Older review',
                    'platform' => 'PC',
                    'tags' => ['Action'],
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        /** @var Game $refreshedGame */
        $refreshedGame = $this->entityManager->getRepository(Game::class)->find('game-1');

        self::assertSame('Clair Obscur', $refreshedGame->getTitle());
        self::assertSame('playing', $refreshedGame->getStatus());
        self::assertSame('Keep this newer review', $refreshedGame->getReview());
    }

    #[TestDox('The sync endpoint applies a newer tombstone and returns the deleted game state')]
    public function testSyncEndpointAppliesANewerTombstoneAndReturnsTheDeletedGameState(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Honkai Star Rail')
            ->setStatus('ongoing')
            ->setReview('')
            ->setPlatform('PC')
            ->setTags([])
            ->setCreatedAt(new DateTimeImmutable('2026-04-23T09:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-04-23T10:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Honkai Star Rail',
                    'status' => 'ongoing',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => '',
                    'platform' => 'PC',
                    'tags' => [],
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T13:00:00Z',
                    'deletedAt' => '2026-04-23T13:00:00Z',
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('2026-04-23T13:00:00Z', $payload['games'][0]['deletedAt']);

        /** @var Game $refreshedGame */
        $refreshedGame = $this->entityManager->getRepository(Game::class)->find('game-1');
        self::assertSame('2026-04-23T13:00:00+00:00', $refreshedGame->getDeletedAt()?->format('Y-m-d\TH:i:sP'));
    }

    #[TestDox('The sync endpoint preserves IGDB metadata when an older client omits IGDB fields')]
    public function testSyncEndpointPreservesIgdbMetadataWhenAnOlderClientOmitsIgdbFields(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Final Fantasy VII Remake Intergrade')
            ->setStatus('playing')
            ->setReview('')
            ->setPlatform('PS5')
            ->setTags(['JRPG'])
            ->setIgdbId(163226)
            ->setIgdbUrl('https://www.igdb.com/games/final-fantasy-vii-remake-intergrade')
            ->setCoverUrl('https://images.igdb.com/igdb/image/upload/t_cover_big/co3b6l.webp')
            ->setIgdbTtbHastilySeconds(108000)
            ->setIgdbTtbNormallySeconds(137700)
            ->setIgdbTtbCompletelySeconds(261000)
            ->setIgdbTtbCount(4)
            ->setIgdbTtbUpdatedAt(new DateTimeImmutable('2026-05-05T08:30:00Z'))
            ->setIgdbDevelopers(['Square Enix Creative Studio I'])
            ->setIgdbPublishers(['Square Enix'])
            ->setIgdbThemes(['Action', 'Fantasy'])
            ->setIgdbGameModes(['Single player'])
            ->setCreatedAt(new DateTimeImmutable('2026-04-23T09:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-04-23T10:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Final Fantasy VII Remake Intergrade',
                    'status' => 'playing',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => '',
                    'platform' => 'PS5',
                    'tags' => ['JRPG'],
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(163226, $payload['games'][0]['igdbId']);
        self::assertSame('https://www.igdb.com/games/final-fantasy-vii-remake-intergrade', $payload['games'][0]['igdbUrl']);
        self::assertSame('https://images.igdb.com/igdb/image/upload/t_cover_big/co3b6l.webp', $payload['games'][0]['coverUrl']);
        self::assertSame(108000, $payload['games'][0]['igdbTtbHastilySeconds']);
        self::assertSame(137700, $payload['games'][0]['igdbTtbNormallySeconds']);
        self::assertSame(261000, $payload['games'][0]['igdbTtbCompletelySeconds']);
        self::assertSame(4, $payload['games'][0]['igdbTtbCount']);
        self::assertSame('2026-05-05T08:30:00Z', $payload['games'][0]['igdbTtbUpdatedAt']);
        self::assertSame(['Square Enix Creative Studio I'], $payload['games'][0]['igdbDevelopers']);
        self::assertSame(['Square Enix'], $payload['games'][0]['igdbPublishers']);
        self::assertSame(['Action', 'Fantasy'], $payload['games'][0]['igdbThemes']);
        self::assertSame(['Single player'], $payload['games'][0]['igdbGameModes']);
    }

    #[TestDox('The sync endpoint preserves ownership type when an older client omits the field')]
    public function testSyncEndpointPreservesOwnershipTypeWhenAnOlderClientOmitsTheField(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Metaphor: ReFantazio')
            ->setStatus('playing')
            ->setReview('')
            ->setPlatform('PS5')
            ->setOwnershipType('both')
            ->setTags(['JRPG'])
            ->setCreatedAt(new DateTimeImmutable('2026-04-23T09:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-04-23T10:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Metaphor: ReFantazio',
                    'status' => 'playing',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => '',
                    'platform' => 'PS5',
                    'tags' => ['JRPG'],
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('both', $payload['games'][0]['ownershipType']);
    }

    #[TestDox('The sync endpoint stores incoming IGDB metadata for a new game')]
    public function testSyncEndpointStoresIncomingIgdbMetadataForANewGame(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Final Fantasy VII Remake Intergrade',
                    'status' => 'playing',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => '',
                    'platform' => 'PS5',
                    'tags' => ['JRPG'],
                    'igdbId' => 163226,
                    'igdbUrl' => 'https://www.igdb.com/games/final-fantasy-vii-remake-intergrade',
                    'coverUrl' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/co3b6l.webp',
                    'igdbTtbHastilySeconds' => 108000,
                    'igdbTtbNormallySeconds' => 137700,
                    'igdbTtbCompletelySeconds' => 261000,
                    'igdbTtbCount' => 4,
                    'igdbTtbUpdatedAt' => '2026-05-05T08:30:00Z',
                    'igdbDevelopers' => ['Square Enix Creative Studio I'],
                    'igdbPublishers' => ['Square Enix'],
                    'igdbThemes' => ['Action', 'Fantasy'],
                    'igdbGameModes' => ['Single player'],
                    'releaseYear' => 2021,
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(163226, $payload['games'][0]['igdbId']);
        self::assertSame('https://www.igdb.com/games/final-fantasy-vii-remake-intergrade', $payload['games'][0]['igdbUrl']);
        self::assertSame('https://images.igdb.com/igdb/image/upload/t_cover_big/co3b6l.webp', $payload['games'][0]['coverUrl']);
        self::assertSame(108000, $payload['games'][0]['igdbTtbHastilySeconds']);
        self::assertSame(137700, $payload['games'][0]['igdbTtbNormallySeconds']);
        self::assertSame(261000, $payload['games'][0]['igdbTtbCompletelySeconds']);
        self::assertSame(4, $payload['games'][0]['igdbTtbCount']);
        self::assertSame('2026-05-05T08:30:00Z', $payload['games'][0]['igdbTtbUpdatedAt']);
        self::assertSame(['Square Enix Creative Studio I'], $payload['games'][0]['igdbDevelopers']);
        self::assertSame(['Square Enix'], $payload['games'][0]['igdbPublishers']);
        self::assertSame(['Action', 'Fantasy'], $payload['games'][0]['igdbThemes']);
        self::assertSame(['Single player'], $payload['games'][0]['igdbGameModes']);
        self::assertSame(2021, $payload['games'][0]['releaseYear']);
    }

    #[TestDox('The sync endpoint keeps server-enriched IGDB metadata when a stale client sends null cache fields')]
    public function testSyncEndpointKeepsServerEnrichedIgdbMetadataWhenStaleClientSendsNullCacheFields(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Final Fantasy XVI')
            ->setStatus('finished')
            ->setRating(null)
            ->setPlayTimeHours(null)
            ->setReview('')
            ->setPlatform('PS5')
            ->setTags(['Action'])
            ->setIgdbId(31551)
            ->setIgdbUrl('https://www.igdb.com/games/final-fantasy-xvi')
            ->setCoverUrl('https://images.igdb.com/igdb/image/upload/t_cover_big/co5xex.webp')
            ->setIgdbTtbCount(8)
            ->setIgdbDevelopers(['Square Enix Creative Business Unit III'])
            ->setIgdbPublishers(['Square Enix'])
            ->setIgdbThemes(['Action', 'Fantasy'])
            ->setIgdbGameModes(['Single player'])
            ->setFinishedAt(new DateTimeImmutable('2026-05-07'))
            ->setCreatedAt(new DateTimeImmutable('2026-05-07T08:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-05-07T09:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Final Fantasy XVI',
                    'status' => 'finished',
                    'rating' => null,
                    'playTimeHours' => 40,
                    'review' => '',
                    'platform' => 'PS5',
                    'tags' => ['Action'],
                    'igdbId' => 31551,
                    'igdbUrl' => null,
                    'coverUrl' => null,
                    'igdbTtbCount' => null,
                    'igdbDevelopers' => null,
                    'igdbPublishers' => null,
                    'igdbThemes' => null,
                    'igdbGameModes' => null,
                    'finishedAt' => '2026-05-07',
                    'createdAt' => '2026-05-07T08:00:00Z',
                    'updatedAt' => '2026-05-07T10:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(40, $payload['games'][0]['playTimeHours']);
        self::assertSame('https://www.igdb.com/games/final-fantasy-xvi', $payload['games'][0]['igdbUrl']);
        self::assertSame('https://images.igdb.com/igdb/image/upload/t_cover_big/co5xex.webp', $payload['games'][0]['coverUrl']);
        self::assertSame(8, $payload['games'][0]['igdbTtbCount']);
        self::assertSame(['Square Enix Creative Business Unit III'], $payload['games'][0]['igdbDevelopers']);
        self::assertSame(['Square Enix'], $payload['games'][0]['igdbPublishers']);
        self::assertSame(['Action', 'Fantasy'], $payload['games'][0]['igdbThemes']);
        self::assertSame(['Single player'], $payload['games'][0]['igdbGameModes']);
    }

    #[TestDox('The sync endpoint clears IGDB metadata when a current client explicitly clears the IGDB ID')]
    public function testSyncEndpointClearsIgdbMetadataWhenCurrentClientExplicitlyClearsIgdbId(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Final Fantasy VII Remake Intergrade')
            ->setStatus('playing')
            ->setReview('')
            ->setPlatform('PS5')
            ->setTags(['JRPG'])
            ->setIgdbId(163226)
            ->setIgdbUrl('https://www.igdb.com/games/final-fantasy-vii-remake-intergrade')
            ->setCoverUrl('https://images.igdb.com/igdb/image/upload/t_cover_big/co3b6l.webp')
            ->setIgdbTtbHastilySeconds(108000)
            ->setIgdbTtbNormallySeconds(137700)
            ->setIgdbTtbCompletelySeconds(261000)
            ->setIgdbTtbCount(4)
            ->setIgdbTtbUpdatedAt(new DateTimeImmutable('2026-05-05T08:30:00Z'))
            ->setIgdbDevelopers(['Square Enix Creative Studio I'])
            ->setIgdbPublishers(['Square Enix'])
            ->setIgdbThemes(['Action', 'Fantasy'])
            ->setIgdbGameModes(['Single player'])
            ->setCreatedAt(new DateTimeImmutable('2026-04-23T09:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-04-23T10:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Final Fantasy VII Remake Intergrade',
                    'status' => 'playing',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => '',
                    'platform' => 'PS5',
                    'tags' => ['JRPG'],
                    'igdbId' => null,
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertNull($payload['games'][0]['igdbId']);
        self::assertNull($payload['games'][0]['igdbUrl']);
        self::assertNull($payload['games'][0]['coverUrl']);
        self::assertNull($payload['games'][0]['igdbTtbHastilySeconds']);
        self::assertNull($payload['games'][0]['igdbTtbNormallySeconds']);
        self::assertNull($payload['games'][0]['igdbTtbCompletelySeconds']);
        self::assertNull($payload['games'][0]['igdbTtbCount']);
        self::assertNull($payload['games'][0]['igdbTtbUpdatedAt']);
        self::assertNull($payload['games'][0]['igdbDevelopers']);
        self::assertNull($payload['games'][0]['igdbPublishers']);
        self::assertNull($payload['games'][0]['igdbThemes']);
        self::assertNull($payload['games'][0]['igdbGameModes']);
    }

    #[TestDox('The sync endpoint lets a client value override a server-enriched release year')]
    public function testSyncEndpointLetsAClientValueOverrideAServerEnrichedReleaseYear(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Chrono Trigger')
            ->setStatus('backlog')
            ->setReview('')
            ->setPlatform('SNES')
            ->setTags([])
            ->setReleaseYear(1995)
            ->setReleaseYearUpdatedAt(new DateTimeImmutable('2026-05-10T00:00:00Z'))
            ->setCreatedAt(new DateTimeImmutable('2026-04-23T09:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-04-23T10:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Chrono Trigger',
                    'status' => 'backlog',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => '',
                    'platform' => 'SNES',
                    'tags' => [],
                    'releaseYear' => 1999,
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        // Client value wins even though the stored per-field timestamp was newer...
        self::assertSame(1999, $payload['games'][0]['releaseYear']);
        // ...and the field is re-stamped to the incoming record's updatedAt so it can't drift.
        self::assertSame('2026-04-23T11:00:00Z', $payload['games'][0]['releaseYearUpdatedAt']);
    }

    #[TestDox('The sync endpoint lets a client clear a client-only field such as developer')]
    public function testSyncEndpointLetsAClientClearAClientOnlyField(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Chrono Trigger')
            ->setStatus('backlog')
            ->setReview('')
            ->setPlatform('SNES')
            ->setTags([])
            ->setDeveloper('Squaresoft')
            ->setDeveloperUpdatedAt(new DateTimeImmutable('2026-05-10T00:00:00Z'))
            ->setCreatedAt(new DateTimeImmutable('2026-04-23T09:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-04-23T10:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Chrono Trigger',
                    'status' => 'backlog',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => '',
                    'platform' => 'SNES',
                    'tags' => [],
                    'developer' => null,
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        // developer has no server-side writer, so a clear wins outright (not gated).
        self::assertNull($payload['games'][0]['developer']);
    }

    #[TestDox('The sync endpoint keeps a server-enriched release year when a stale client clears it without a timestamp')]
    public function testSyncEndpointKeepsAServerEnrichedReleaseYearWhenAStaleClientClearsItWithoutATimestamp(): void
    {
        $auth = $this->createUserWithSyncToken();

        $existingGame = (new Game())
            ->setId('game-1')
            ->setUser($auth['user'])
            ->setTitle('Chrono Trigger')
            ->setStatus('backlog')
            ->setReview('')
            ->setPlatform('SNES')
            ->setTags([])
            ->setReleaseYear(1995)
            ->setReleaseYearUpdatedAt(new DateTimeImmutable('2026-05-10T00:00:00Z'))
            ->setCreatedAt(new DateTimeImmutable('2026-04-23T09:00:00Z'))
            ->setUpdatedAt(new DateTimeImmutable('2026-04-23T10:00:00Z'));

        $this->entityManager->persist($existingGame);
        $this->entityManager->flush();

        $this->postJson('/api/sync', [
            'games' => [
                [
                    'id' => 'game-1',
                    'title' => 'Chrono Trigger',
                    'status' => 'backlog',
                    'rating' => null,
                    'playTimeHours' => null,
                    'review' => '',
                    'platform' => 'SNES',
                    'tags' => [],
                    'releaseYear' => null,
                    'finishedAt' => null,
                    'createdAt' => '2026-04-23T09:00:00Z',
                    'updatedAt' => '2026-04-23T11:00:00Z',
                    'deletedAt' => null,
                ],
            ],
            'logs' => [],
        ], $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        // Clearing to null stays timestamp-gated: a stale client without a per-field
        // timestamp cannot wipe the server-enriched value.
        self::assertSame(1995, $payload['games'][0]['releaseYear']);
    }

    #[TestDox('The sync endpoint rejects log entries that reference an unknown game')]
    public function testSyncEndpointRejectsLogEntriesThatReferenceAnUnknownGame(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/sync', [
            'games' => [],
            'logs' => [
                [
                    'id' => 'log-404',
                    'gameId' => 'missing-game',
                    'content' => 'This should fail.',
                    'createdAt' => '2026-04-23T10:30:00Z',
                    'updatedAt' => '2026-04-23T10:30:00Z',
                    'deletedAt' => null,
                ],
            ],
        ], $auth['plainToken']);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'error' => 'Unknown gameId "missing-game" in log sync payload.',
            ], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }
}
