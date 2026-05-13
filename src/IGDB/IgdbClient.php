<?php

namespace App\IGDB;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IgdbClient
{
    private ?string $accessToken = null;
    private ?int $accessTokenExpiresAt = null;
    private ?string $lastAuthenticationError = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim((string) $this->clientId) && '' !== trim((string) $this->clientSecret);
    }

    public function authenticationError(): ?string
    {
        if (!$this->isConfigured()) {
            return 'IGDB_CLIENT_ID and/or IGDB_CLIENT_SECRET are missing.';
        }

        if (null !== $this->getAccessToken()) {
            return null;
        }

        return $this->lastAuthenticationError ?? 'Twitch authentication failed for an unknown reason.';
    }

    public function fetchGame(int $igdbId): ?IgdbGameMetadata
    {
        if ($igdbId <= 0 || !$this->isConfigured()) {
            return null;
        }

        $accessToken = $this->getAccessToken();

        if (null === $accessToken) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                    'Client-ID' => trim((string) $this->clientId),
                ],
                'body' => sprintf(
                    'fields name,url,cover.image_id,first_release_date,involved_companies.developer,involved_companies.publisher,involved_companies.company.name,themes.name,game_modes.name; where id = %d; limit 1;',
                    $igdbId,
                ),
            ]);

            $payload = $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }

        if (!isset($payload[0]) || !is_array($payload[0])) {
            return null;
        }

        $game = $payload[0];
        $cover = isset($game['cover']) && is_array($game['cover']) ? $game['cover'] : [];
        $releaseTimestamp = isset($game['first_release_date']) && is_int($game['first_release_date'])
            ? $game['first_release_date']
            : null;

        return new IgdbGameMetadata(
            $igdbId,
            isset($game['name']) && is_string($game['name']) ? $game['name'] : '',
            isset($game['url']) && is_string($game['url']) ? $game['url'] : null,
            isset($cover['image_id']) && is_string($cover['image_id']) ? $cover['image_id'] : null,
            null !== $releaseTimestamp ? (new \DateTimeImmutable())->setTimestamp($releaseTimestamp) : null,
            $this->companyNames($game, 'developer'),
            $this->companyNames($game, 'publisher'),
            $this->nestedNames($game, 'themes'),
            $this->nestedNames($game, 'game_modes'),
        );
    }

    public function fetchTimeToBeat(int $igdbId): ?IgdbTimeToBeat
    {
        if ($igdbId <= 0 || !$this->isConfigured()) {
            return null;
        }

        $accessToken = $this->getAccessToken();

        if (null === $accessToken) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.igdb.com/v4/game_time_to_beats', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                    'Client-ID' => trim((string) $this->clientId),
                ],
                'body' => sprintf(
                    'fields game_id,hastily,normally,completely,count,updated_at; where game_id = %d; limit 1;',
                    $igdbId,
                ),
            ]);

            $payload = $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }

        if (!isset($payload[0]) || !is_array($payload[0])) {
            return new IgdbTimeToBeat($igdbId, null, null, null, 0, null);
        }

        $timeToBeat = $payload[0];
        $updatedAtTimestamp = isset($timeToBeat['updated_at']) && is_int($timeToBeat['updated_at'])
            ? $timeToBeat['updated_at']
            : null;

        return new IgdbTimeToBeat(
            $igdbId,
            $this->positiveIntField($timeToBeat, 'hastily'),
            $this->positiveIntField($timeToBeat, 'normally'),
            $this->positiveIntField($timeToBeat, 'completely'),
            $this->positiveIntField($timeToBeat, 'count'),
            null !== $updatedAtTimestamp ? (new \DateTimeImmutable())->setTimestamp($updatedAtTimestamp) : null,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function positiveIntField(array $payload, string $field): ?int
    {
        if (!isset($payload[$field]) || !is_int($payload[$field]) || $payload[$field] <= 0) {
            return null;
        }

        return $payload[$field];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function companyNames(array $payload, string $role): array
    {
        $companies = isset($payload['involved_companies']) && is_array($payload['involved_companies'])
            ? $payload['involved_companies']
            : [];
        $names = [];

        foreach ($companies as $company) {
            if (!is_array($company) || true !== ($company[$role] ?? false)) {
                continue;
            }

            $companyDetails = isset($company['company']) && is_array($company['company'])
                ? $company['company']
                : [];

            if (isset($companyDetails['name']) && is_string($companyDetails['name'])) {
                $names[] = $companyDetails['name'];
            }
        }

        return $this->uniqueNonEmptyStrings($names);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function nestedNames(array $payload, string $field): array
    {
        $items = isset($payload[$field]) && is_array($payload[$field]) ? $payload[$field] : [];
        $names = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                $names[] = $item['name'];
            }
        }

        return $this->uniqueNonEmptyStrings($names);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function uniqueNonEmptyStrings(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $trimmed = trim($value);

            if ('' !== $trimmed) {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function getAccessToken(): ?string
    {
        if (
            null !== $this->accessToken
            && null !== $this->accessTokenExpiresAt
            && time() < $this->accessTokenExpiresAt - 60
        ) {
            return $this->accessToken;
        }

        $this->lastAuthenticationError = null;

        try {
            $response = $this->httpClient->request('POST', 'https://id.twitch.tv/oauth2/token', [
                'body' => [
                    'client_id' => trim((string) $this->clientId),
                    'client_secret' => trim((string) $this->clientSecret),
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            $this->lastAuthenticationError = sprintf('Twitch token request failed: %s', $exception->getMessage());

            return null;
        } catch (ExceptionInterface $exception) {
            $this->lastAuthenticationError = sprintf('Twitch token response could not be read: %s', $exception->getMessage());

            return null;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->lastAuthenticationError = sprintf(
                'Twitch token endpoint returned HTTP %d: %s',
                $statusCode,
                $this->summarizeResponseBody($body),
            );

            return null;
        }

        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            $this->lastAuthenticationError = sprintf('Twitch token endpoint returned invalid JSON: %s', json_last_error_msg());

            return null;
        }

        if (!isset($payload['access_token']) || !is_string($payload['access_token'])) {
            $this->lastAuthenticationError = sprintf(
                'Twitch token response did not include an access_token: %s',
                $this->summarizeResponseBody($body),
            );

            return null;
        }

        $expiresIn = isset($payload['expires_in']) && is_numeric($payload['expires_in'])
            ? (int) $payload['expires_in']
            : 0;

        $this->accessToken = $payload['access_token'];
        $this->accessTokenExpiresAt = time() + max($expiresIn, 0);

        return $this->accessToken;
    }

    private function summarizeResponseBody(string $body): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($body));

        if (!is_string($normalized) || '' === $normalized) {
            return 'empty response body';
        }

        return mb_strlen($normalized) > 300 ? mb_substr($normalized, 0, 300).'...' : $normalized;
    }
}
