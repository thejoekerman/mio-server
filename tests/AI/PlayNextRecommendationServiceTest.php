<?php

namespace App\Tests\AI;

use App\AI\PlayNextRecommendationService;
use App\Entity\Game;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

final class PlayNextRecommendationServiceTest extends TestCase
{
    #[TestDox('Play-next recommendations report when no backlog candidates are available')]
    public function testPlayNextRecommendationsReportWhenNoBacklogCandidatesAreAvailable(): void
    {
        $service = new PlayNextRecommendationService(
            $this->createFailingAgent(),
            $this->createFailingAgent(),
            'test',
            'gemini',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No backlog candidates are available for a recommendation.');

        $service->recommend([]);
    }

    #[TestDox('Play-next recommendations shortlist large backlogs before calling the model')]
    public function testPlayNextRecommendationsShortlistLargeBacklogsBeforeCallingTheModel(): void
    {
        $agent = $this->createInspectingAgent();
        $service = new PlayNextRecommendationService(
            $agent,
            $this->createFailingAgent(),
            'test',
            'lmstudio',
        );

        $libraryGames = [
            $this->createGame('finished-1', 'Recent JRPG', 'finished', 'Switch', ['JRPG'], '-30 days'),
        ];

        for ($index = 1; $index <= 35; ++$index) {
            $libraryGames[] = $this->createGame(
                sprintf('backlog-%02d', $index),
                sprintf('Backlog Game %02d', $index),
                'backlog',
                0 === $index % 2 ? 'PS5' : 'Switch',
                0 === $index % 3 ? ['JRPG'] : ['Action'],
                sprintf('-%d days', $index),
            );
        }

        $recommendations = $service->recommend($libraryGames);

        self::assertCount(2, $recommendations);
        self::assertLessThanOrEqual(20, substr_count($agent->prompt, 'id=backlog-'));
        self::assertStringContainsString('shortlisted from 35 available backlog games', $agent->prompt);
    }

    private function createGame(
        string $id,
        string $title,
        string $status,
        string $platform,
        array $tags = [],
        string $updatedAt = '-1 day',
    ): Game {
        $date = new \DateTimeImmutable($updatedAt);

        return (new Game())
            ->setId($id)
            ->setTitle($title)
            ->setStatus($status)
            ->setPlatform($platform)
            ->setTags($tags)
            ->setReview('')
            ->setCreatedAt($date)
            ->setUpdatedAt($date)
            ->setFinishedAt('finished' === $status ? $date : null);
    }

    private function createInspectingAgent(): AgentInterface
    {
        return new class implements AgentInterface {
            public string $prompt = '';

            public function call(MessageBag $messages, array $options = []): ResultInterface
            {
                $this->prompt = $messages->getUserMessage()?->asText() ?? '';

                preg_match_all('/id=(backlog-\d+)/', $this->prompt, $matches);
                $ids = $matches[1] ?? [];

                return new TextResult(json_encode([
                    'recommendations' => [
                        ['slot' => 'continue', 'gameId' => $ids[0], 'reason' => 'A grounded continuation.'],
                        ['slot' => 'alternate', 'gameId' => $ids[1], 'reason' => 'A lighter backup.'],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            public function getName(): string
            {
                return 'inspecting-test-agent';
            }
        };
    }

    private function createFailingAgent(): AgentInterface
    {
        return new class implements AgentInterface {
            public function call(MessageBag $messages, array $options = []): ResultInterface
            {
                throw new \LogicException('The agent should not be called without backlog candidates.');
            }

            public function getName(): string
            {
                return 'failing-test-agent';
            }
        };
    }
}
