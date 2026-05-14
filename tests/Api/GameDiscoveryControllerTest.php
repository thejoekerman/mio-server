<?php

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\TestDox;

final class GameDiscoveryControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        $_ENV['AI_PROVIDER'] = 'none';
        $_SERVER['AI_PROVIDER'] = 'none';

        parent::setUp();
    }

    #[TestDox('The game discovery endpoint rejects requests without a sync token')]
    public function testGameDiscoveryEndpointRejectsRequestsWithoutASyncToken(): void
    {
        $this->postJson('/api/ai/discover-games', []);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Missing sync token.'], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }

    #[TestDox('The game discovery endpoint reports when recommendations are unavailable')]
    public function testGameDiscoveryEndpointReportsWhenRecommendationsAreUnavailable(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/ai/discover-games', [], $auth['plainToken']);

        self::assertSame(503, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'error' => 'Game discovery recommendations are not available on this backend.',
            ], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }
}
