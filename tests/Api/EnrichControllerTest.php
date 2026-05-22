<?php

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\TestDox;

final class EnrichControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        $_ENV['IGDB_CLIENT_ID'] = '';
        $_ENV['IGDB_CLIENT_SECRET'] = '';
        $_SERVER['IGDB_CLIENT_ID'] = '';
        $_SERVER['IGDB_CLIENT_SECRET'] = '';

        parent::setUp();
    }

    #[TestDox('The enrich endpoint rejects requests without a sync token')]
    public function testEnrichEndpointRejectsRequestsWithoutASyncToken(): void
    {
        $this->postJson('/api/enrich', []);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Missing sync token.'], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }

    #[TestDox('The enrich endpoint reports failure for an authenticated user when IGDB is not configured')]
    public function testEnrichEndpointReportsFailureWhenIgdbIsNotConfigured(): void
    {
        $auth = $this->createUserWithSyncToken();

        $this->postJson('/api/enrich', [], $auth['plainToken']);

        self::assertSame(500, $this->client->getResponse()->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Enrichment failed.'], JSON_THROW_ON_ERROR),
            $this->client->getResponse()->getContent() ?: '',
        );
    }
}
