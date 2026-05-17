<?php

namespace App\Tests\Api;

use App\Entity\SyncToken;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        $kernel = static::bootKernel();
        $this->client = new KernelBrowser($kernel);
        /** @var ManagerRegistry $doctrine */
        $doctrine = $kernel->getContainer()->get('doctrine');
        $this->entityManager = $doctrine->getManager();

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->clear();
        self::ensureKernelShutdown();
    }

    /**
     * @return array{user: User, plainToken: string}
     */
    protected function createUserWithSyncToken(
        string $email = 'you@example.com',
        string $plainToken = 'test-sync-token',
        string $tokenName = 'Test device',
        bool $aiUsage = false,
    ): array {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName($email)
            ->setAiUsage($aiUsage);

        $token = (new SyncToken())
            ->setUser($user)
            ->setName($tokenName)
            ->setTokenHash(SyncToken::hashPlainToken($plainToken));

        $this->entityManager->persist($user);
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return [
            'user' => $user,
            'plainToken' => $plainToken,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postJson(string $uri, array $payload, ?string $plainToken = null): void
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if (null !== $plainToken) {
            $server['HTTP_AUTHORIZATION'] = sprintf('Bearer %s', $plainToken);
        }

        $this->client->request(
            'POST',
            $uri,
            [],
            [],
            $server,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    protected function getJson(string $uri, ?string $plainToken = null): void
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
        ];

        if (null !== $plainToken) {
            $server['HTTP_AUTHORIZATION'] = sprintf('Bearer %s', $plainToken);
        }

        $this->client->request('GET', $uri, [], [], $server);
    }

    private function resetDatabase(): void
    {
        $databaseName = (string) $this->entityManager->getConnection()->getDatabase();

        if (!str_ends_with($databaseName, '_test')) {
            throw new \RuntimeException(sprintf(
                'Refusing to reset non-test database "%s". Check PHPUnit DATABASE_URL.',
                $databaseName,
            ));
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool->dropDatabase();

        if ([] !== $metadata) {
            $schemaTool->createSchema($metadata);
        }
    }
}
