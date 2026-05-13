<?php

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\TestDox;

final class PlayNextControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_AI_PLAY_NEXT_PROVIDER'] = 'none';
        $_SERVER['APP_AI_PLAY_NEXT_PROVIDER'] = 'none';

        parent::setUp();
    }

    #[TestDox('The play-next endpoint rejects requests without a sync token')]
    public function testPlayNextEndpointRejectsRequestsWithoutASyncToken(): void
    {
        $this->postJson('/api/ai/play-next', []);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Missing sync token.'], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }

    #[TestDox('The play-next endpoint reports when recommendations are unavailable')]
    public function testPlayNextEndpointReportsWhenRecommendationsAreUnavailable(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/ai/play-next', [], $auth['plainToken']);

        self::assertSame(503, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'error' => 'Play-next recommendations are not available on this backend.',
            ], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }
}
