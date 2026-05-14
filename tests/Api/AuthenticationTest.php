<?php

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\TestDox;

final class AuthenticationTest extends ApiTestCase
{
    #[TestDox('The me endpoint rejects requests without a sync token')]
    public function testMeEndpointRejectsRequestsWithoutASyncToken(): void
    {
        $this->getJson('/api/me');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Missing sync token.'], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }

    #[TestDox('The me endpoint returns the authenticated user for a valid sync token')]
    public function testMeEndpointReturnsTheAuthenticatedUserForAValidSyncToken(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->getJson('/api/me', $auth['plainToken']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $payload = json_decode($this->client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($auth['user']->getId(), $payload['user']['id']);
        self::assertSame('you@example.com', $payload['user']['email']);
        self::assertSame('you@example.com', $payload['user']['displayName']);
        self::assertIsBool($payload['capabilities']['reviewDraft']);
        self::assertIsBool($payload['capabilities']['playNext']);
        self::assertIsBool($payload['capabilities']['gameDiscovery']);
    }
}
