<?php

namespace App\Tests\Command;

use App\Entity\SyncDeletion;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[TestDox('UserCommand Test')]
class UserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();

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

    public function testListUsersShowsIdsAndAiUsage(): void
    {
        $this->createUser('first@example.com', 'First User', false);
        $this->createUser('second@example.com', 'Second User', true);

        $tester = $this->runCommand('app:user:list');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('first@example.com', $tester->getDisplay());
        self::assertStringContainsString('second@example.com', $tester->getDisplay());
        self::assertStringContainsString('First User', $tester->getDisplay());
        self::assertStringContainsString('Second User', $tester->getDisplay());
        self::assertStringContainsString('yes', $tester->getDisplay());
        self::assertStringContainsString('no', $tester->getDisplay());
    }

    public function testSetUserAiUsageEnablesAndDisablesById(): void
    {
        $user = $this->createUser('ai-toggle@example.com', 'AI Toggle', false);
        $id = (string) $user->getId();

        $enableTester = $this->runCommand('app:user:ai-usage', [
            'id' => $id,
            'enabled' => 'yes',
        ]);

        self::assertSame(Command::SUCCESS, $enableTester->getStatusCode());
        $this->entityManager->clear();

        $enabledUser = $this->entityManager->find(User::class, (int) $id);
        self::assertInstanceOf(User::class, $enabledUser);
        self::assertTrue($enabledUser->getAiUsage());

        $disableTester = $this->runCommand('app:user:ai-usage', [
            'id' => $id,
            'enabled' => 'no',
        ]);

        self::assertSame(Command::SUCCESS, $disableTester->getStatusCode());
        $this->entityManager->clear();

        $disabledUser = $this->entityManager->find(User::class, (int) $id);
        self::assertInstanceOf(User::class, $disabledUser);
        self::assertFalse($disabledUser->getAiUsage());
    }

    public function testSetUserAiUsageRejectsInvalidInput(): void
    {
        $badIdTester = $this->runCommand('app:user:ai-usage', [
            'id' => 'email@example.com',
            'enabled' => 'yes',
        ]);

        self::assertSame(Command::INVALID, $badIdTester->getStatusCode());
        self::assertStringContainsString('User id must be a positive integer.', $badIdTester->getDisplay());

        $badFlagTester = $this->runCommand('app:user:ai-usage', [
            'id' => '1',
            'enabled' => 'maybe',
        ]);

        self::assertSame(Command::INVALID, $badFlagTester->getStatusCode());
        self::assertStringContainsString('AI usage must be one of', $badFlagTester->getDisplay());
    }

    public function testPurgeSyncDeletionsAdvancesTheUserCursorFloor(): void
    {
        $user = $this->createUser('sync@example.com', 'Sync User', false);
        $deletion = (new SyncDeletion())
            ->setUser($user)
            ->setEntityType('game')
            ->setEntityId('old-game')
            ->setUpdatedAt(new \DateTimeImmutable('-200 days'))
            ->setDeletedAt(new \DateTimeImmutable('-200 days'))
            ->setRevision(42);
        $this->entityManager->persist($deletion);
        $this->entityManager->flush();

        $tester = $this->runCommand('app:sync:purge-deletions', ['--days' => '180']);
        $this->entityManager->clear();
        $reloadedUser = $this->entityManager->find(User::class, $user->getId());

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Purged 1 sync deletion marker', $tester->getDisplay());
        self::assertSame([], $this->entityManager->getRepository(SyncDeletion::class)->findAll());
        self::assertInstanceOf(User::class, $reloadedUser);
        self::assertSame(42, $reloadedUser->getMinimumSupportedCursor());
    }

    /**
     * @param array<string, string> $input
     */
    private function runCommand(string $name, array $input = []): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find($name);
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    private function createUser(string $email, string $displayName, bool $aiUsage): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName($displayName)
            ->setAiUsage($aiUsage);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
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
