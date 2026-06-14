<?php

namespace App\Tests\Api;

use App\Entity\Game;
use App\Entity\Journey;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('SyncController Test')]
final class SyncControllerTest extends ApiTestCase
{
    #[TestDox('The sync endpoint creates and returns the canonical 3.0 graph')]
    public function testCreatesCanonicalGraph(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/sync', $this->request(
            games: [$this->game()],
            journeys: [$this->journey()],
            logs: [$this->log()],
            earnedTrophies: [$this->earnedTrophy()],
        ), $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $payload = $this->responsePayload();

        self::assertSame(['game-1'], $payload['acknowledged']['games']);
        self::assertSame(['journey-1'], $payload['acknowledged']['journeys']);
        self::assertSame(['log-1'], $payload['acknowledged']['logs']);
        self::assertSame(['trophy-1'], $payload['acknowledged']['earnedTrophies']);
        self::assertSame('https://images.example/cover.webp', $payload['changes']['games'][0]['cover']['url']);
        self::assertSame('game-1', $payload['changes']['journeys'][0]['gameId']);
        self::assertSame('journey-1', $payload['changes']['logs'][0]['journeyId']);
        self::assertSame(['games'], $payload['changes']['games'][0]['tags']);
        self::assertSame(['games' => 1, 'journeys' => 1, 'logs' => 1], $payload['totals']);
        self::assertGreaterThan(0, $payload['cursor']);
    }

    #[TestDox('The sync endpoint returns only changes after the client cursor')]
    public function testReturnsOnlyChangesAfterCursor(): void
    {
        $auth = $this->createUserWithSyncToken();
        $this->postJson('/api/sync', $this->request(games: [$this->game()]), $auth['plainToken']);
        $cursor = $this->responsePayload()['cursor'];

        $this->postJson('/api/sync', $this->request(cursor: $cursor, full: false), $auth['plainToken']);
        $payload = $this->responsePayload();

        self::assertSame($cursor, $payload['cursor']);
        self::assertSame([], $payload['changes']['games']);
        self::assertSame([], $payload['changes']['journeys']);
        self::assertSame([], $payload['changes']['logs']);
    }

    #[TestDox('The sync endpoint returns the authoritative server record when a stale client loses')]
    public function testReturnsAuthoritativeRecordForStaleSubmission(): void
    {
        $auth = $this->createUserWithSyncToken();
        $newer = $this->game(['title' => 'Server wins', 'updatedAt' => '2026-06-14T12:00:00Z']);
        $this->postJson('/api/sync', $this->request(games: [$newer]), $auth['plainToken']);
        $cursor = $this->responsePayload()['cursor'];

        $stale = $this->game(['title' => 'Stale device', 'updatedAt' => '2026-06-14T11:00:00Z']);
        $this->postJson('/api/sync', $this->request(games: [$stale], cursor: $cursor, full: false), $auth['plainToken']);
        $payload = $this->responsePayload();

        self::assertSame(['game-1'], $payload['acknowledged']['games']);
        self::assertSame('Server wins', $payload['changes']['games'][0]['title']);
        self::assertSame($cursor, $payload['cursor']);
    }

    #[TestDox('The sync endpoint rolls back the whole request when a child references an unknown parent')]
    public function testRollsBackInvalidGraph(): void
    {
        $auth = $this->createUserWithSyncToken();
        $invalidJourney = $this->journey(['gameId' => 'missing']);

        $this->postJson('/api/sync', $this->request(
            games: [$this->game()],
            journeys: [$invalidJourney],
        ), $auth['plainToken']);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->entityManager->getRepository(Game::class)->find('game-1'));
        self::assertNull($this->entityManager->getRepository(Journey::class)->find('journey-1'));
    }

    /** @return array<string, mixed> */
    private function request(
        array $games = [],
        array $journeys = [],
        array $logs = [],
        array $earnedTrophies = [],
        ?int $cursor = null,
        bool $full = true,
    ): array {
        return [
            'cursor' => $cursor,
            'full' => $full,
            'changes' => [
                'games' => $games,
                'journeys' => $journeys,
                'logs' => $logs,
                'earnedTrophies' => $earnedTrophies,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function game(array $overrides = []): array
    {
        return array_replace([
            'id' => 'game-1',
            'title' => 'Final Fantasy VII',
            'releaseYear' => 1997,
            'developers' => ['Square'],
            'publishers' => ['Square'],
            'genres' => ['RPG'],
            'themes' => ['Fantasy'],
            'gameModes' => ['Single player'],
            'tags' => ['games'],
            'cover' => [
                'url' => 'https://images.example/cover.webp',
                'source' => ['provider' => 'manual', 'pageUrl' => null],
            ],
            'externalReferences' => [],
            'playtimeEstimates' => null,
            'metadataReviewedAt' => null,
            'createdAt' => '2026-06-14T10:00:00Z',
            'updatedAt' => '2026-06-14T10:00:00Z',
            'deletedAt' => null,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function journey(array $overrides = []): array
    {
        return array_replace([
            'id' => 'journey-1',
            'gameId' => 'game-1',
            'status' => 'playing',
            'platform' => 'PS5',
            'ownershipType' => 'physical',
            'priority' => 'high-interest',
            'rating' => null,
            'review' => '',
            'playTimeHours' => 12.5,
            'startedAt' => '2026-06-01',
            'finishedAt' => null,
            'pausedAt' => null,
            'nudgeAt' => null,
            'createdAt' => '2026-06-14T10:00:00Z',
            'updatedAt' => '2026-06-14T10:00:00Z',
            'deletedAt' => null,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function log(array $overrides = []): array
    {
        return array_replace([
            'id' => 'log-1',
            'journeyId' => 'journey-1',
            'content' => 'Reached Midgar.',
            'createdAt' => '2026-06-14T10:00:00Z',
            'updatedAt' => '2026-06-14T10:00:00Z',
            'deletedAt' => null,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function earnedTrophy(array $overrides = []): array
    {
        return array_replace([
            'id' => 'trophy-1',
            'trophyId' => 'first-log',
            'earnedAt' => '2026-06-14T10:00:00Z',
            'gameId' => 'game-1',
            'context' => ['source' => 'test'],
            'createdAt' => '2026-06-14T10:00:00Z',
            'updatedAt' => '2026-06-14T10:00:00Z',
            'deletedAt' => null,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function responsePayload(): array
    {
        return json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
    }
}
