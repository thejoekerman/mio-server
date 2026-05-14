<?php

namespace App\Tests\AI;

use App\AI\GameDiscoveryRecommendationService;
use App\Entity\Game;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

final class GameDiscoveryRecommendationServiceTest extends TestCase
{
    #[TestDox('Game discovery reports when no backlog platforms are available')]
    public function testGameDiscoveryReportsWhenNoBacklogPlatformsAreAvailable(): void
    {
        $service = new GameDiscoveryRecommendationService(
            $this->createFailingAgent(),
            $this->createFailingAgent(),
            'test',
            'gemini',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No backlog platforms are available for discovery.');

        $service->recommend([
            $this->createGame('finished-1', 'Finished PC Game', 'finished', 'PC'),
        ]);
    }

    #[TestDox('Game discovery returns one suggestion with matching backlog platforms')]
    public function testGameDiscoveryReturnsOneSuggestionWithMatchingBacklogPlatforms(): void
    {
        $service = new GameDiscoveryRecommendationService(
            $this->createTextAgent(json_encode([
                'recommendations' => [
                    [
                        'title' => 'Octopath Traveler II',
                        'platforms' => ['Switch'],
                        'reason' => 'The party-driven structure lines up with your recent JRPG notes without being another unfinished backlog entry.',
                        'igdbUrl' => 'https://www.igdb.com/games/octopath-traveler-ii',
                        'ttbNormallySeconds' => 216000,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            $this->createFailingAgent(),
            'test',
            'lmstudio',
        );

        $recommendations = $service->recommend([
            $this->createGame('backlog-1', 'Final Fantasy IX', 'backlog', 'Switch', ['JRPG']),
            $this->createGame('backlog-2', 'Metaphor: ReFantazio', 'backlog', 'PS5', ['JRPG']),
            $this->createGame('finished-1', 'Persona 5 Royal', 'finished', 'PC', ['JRPG'], 5),
        ]);

        self::assertCount(1, $recommendations);
        self::assertSame('Octopath Traveler II', $recommendations[0]->title);
        self::assertSame(['Switch'], $recommendations[0]->platforms);
        self::assertSame('https://www.igdb.com/games/octopath-traveler-ii', $recommendations[0]->igdbUrl);
        self::assertSame(216000, $recommendations[0]->ttbNormallySeconds);
    }

    #[TestDox('Game discovery skips existing games and returns a usable new suggestion')]
    public function testGameDiscoverySkipsExistingGamesAndReturnsAUsableNewSuggestion(): void
    {
        $service = new GameDiscoveryRecommendationService(
            $this->createTextAgent(json_encode([
                'recommendations' => [
                    [
                        'title' => 'Final Fantasy IX',
                        'platforms' => ['Switch'],
                        'reason' => 'This is already there.',
                        'igdbUrl' => null,
                        'ttbNormallySeconds' => null,
                    ],
                    [
                        'title' => 'Chained Echoes',
                        'platforms' => ['Switch'],
                        'reason' => 'A different RPG suggestion.',
                        'igdbUrl' => null,
                        'ttbNormallySeconds' => null,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            $this->createFailingAgent(),
            'test',
            'lmstudio',
        );

        $recommendations = $service->recommend([
            $this->createGame('backlog-1', 'Final Fantasy IX', 'backlog', 'Switch', ['JRPG']),
        ]);

        self::assertCount(1, $recommendations);
        self::assertSame('Chained Echoes', $recommendations[0]->title);
    }

    #[TestDox('Game discovery reports when no usable new suggestion survives')]
    public function testGameDiscoveryReportsWhenNoUsableNewSuggestionSurvives(): void
    {
        $service = new GameDiscoveryRecommendationService(
            $this->createTextAgent(json_encode([
                'recommendations' => [
                    [
                        'title' => 'Final Fantasy IX',
                        'platforms' => ['Switch'],
                        'reason' => 'This is already there.',
                        'igdbUrl' => null,
                        'ttbNormallySeconds' => null,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            $this->createFailingAgent(),
            'test',
            'lmstudio',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Game-discovery suggester did not return a usable new recommendation.');

        $service->recommend([
            $this->createGame('backlog-1', 'Final Fantasy IX', 'backlog', 'Switch', ['JRPG']),
        ]);
    }

    private function createGame(
        string $id,
        string $title,
        string $status,
        string $platform,
        array $tags = [],
        ?int $rating = null,
    ): Game {
        return (new Game())
            ->setId($id)
            ->setTitle($title)
            ->setStatus($status)
            ->setPlatform($platform)
            ->setTags($tags)
            ->setRating($rating)
            ->setReview('');
    }

    private function createTextAgent(string $content): AgentInterface
    {
        return new class($content) implements AgentInterface {
            public function __construct(private readonly string $content)
            {
            }

            public function call(MessageBag $messages, array $options = []): ResultInterface
            {
                return new TextResult($this->content);
            }

            public function getName(): string
            {
                return 'text-test-agent';
            }
        };
    }

    private function createFailingAgent(): AgentInterface
    {
        return new class implements AgentInterface {
            public function call(MessageBag $messages, array $options = []): ResultInterface
            {
                throw new \LogicException('The agent should not be called.');
            }

            public function getName(): string
            {
                return 'failing-test-agent';
            }
        };
    }
}
