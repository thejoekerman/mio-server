<?php

namespace App\Tests\Api;

use App\Entity\Game;
use App\Entity\Journey;
use App\Entity\LogEntry;
use App\Entity\SyncDeletion;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('SyncController Test')]
final class SyncControllerTest extends ApiTestCase
{
    #[TestDox('The sync endpoint rejects clients without the v3 deletion protocol')]
    public function testRejectsOlderSyncProtocols(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/sync', [
            'cursor' => null,
            'full' => true,
            'changes' => [],
        ], $auth['plainToken']);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('protocol v3 is required', $this->client->getResponse()->getContent() ?: '');
    }

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

    #[TestDox('The sync endpoint hard-deletes records and returns compact deletion markers')]
    public function testHardDeletesRecordsAndReturnsDeletionMarkers(): void
    {
        $auth = $this->createUserWithSyncToken();
        $this->postJson('/api/sync', $this->request(
            games: [$this->game()],
            journeys: [$this->journey()],
            logs: [$this->log()],
        ), $auth['plainToken']);
        $cursor = $this->responsePayload()['cursor'];
        $deletedAt = '2026-06-15T10:00:00.345Z';

        $this->postJson('/api/sync', $this->request(
            games: [$this->game(['updatedAt' => $deletedAt, 'deletedAt' => $deletedAt])],
            journeys: [$this->journey(['updatedAt' => $deletedAt, 'deletedAt' => $deletedAt])],
            logs: [$this->log(['updatedAt' => $deletedAt, 'deletedAt' => $deletedAt])],
            cursor: $cursor,
            full: false,
        ), $auth['plainToken']);
        $payload = $this->responsePayload();

        self::assertNull($this->entityManager->getRepository(Game::class)->find('game-1'));
        self::assertNull($this->entityManager->getRepository(Journey::class)->find('journey-1'));
        self::assertNull($this->entityManager->getRepository(LogEntry::class)->find('log-1'));
        self::assertCount(3, $this->entityManager->getRepository(SyncDeletion::class)->findAll());
        self::assertSame([['id' => 'game-1', 'updatedAt' => $deletedAt]], $payload['deletions']['games']);
        self::assertSame([], $payload['changes']['games']);
        self::assertSame(['games' => 0, 'journeys' => 0, 'logs' => 0], $payload['totals']);
    }

    #[TestDox('The sync endpoint requires authoritative recovery below the retained cursor floor')]
    public function testRequiresAuthoritativeRecoveryBelowCursorFloor(): void
    {
        $auth = $this->createUserWithSyncToken();
        $this->postJson('/api/sync', $this->request(games: [$this->game(['title' => 'Server copy'])]), $auth['plainToken']);
        $cursor = $this->responsePayload()['cursor'];
        $auth['user']->advanceMinimumSupportedCursor($cursor);
        $this->entityManager->flush();

        $this->postJson('/api/sync', $this->request(
            games: [$this->game(['title' => 'Stale browser', 'updatedAt' => '2026-06-15T12:00:00Z'])],
            cursor: 0,
            full: false,
        ), $auth['plainToken']);
        $payload = $this->responsePayload();

        self::assertTrue($payload['recoveryRequired']);
        self::assertSame([], $payload['acknowledged']['games']);
        self::assertSame('Server copy', $payload['changes']['games'][0]['title']);
        self::assertSame([], $payload['deletions']['games']);
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
            'protocolVersion' => 3,
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
