<?php

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\TestDox;

final class ReviewDraftControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        $_ENV['AI_PROVIDER'] = 'lmstudio';
        $_SERVER['AI_PROVIDER'] = 'lmstudio';
        $_ENV['LMSTUDIO_HOST_URL'] = 'http://localhost:1234';
        $_SERVER['LMSTUDIO_HOST_URL'] = 'http://localhost:1234';

        parent::setUp();
    }

    #[TestDox('The review-draft endpoint rejects users without AI enabled')]
    public function testReviewDraftEndpointRejectsUsersWithoutAiEnabled(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/ai/review-draft/missing-game', [], $auth['plainToken']);

        self::assertSame(503, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'error' => 'Review drafting is not available on this backend.',
            ], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }
}
