<?php

namespace App\Tests\AI;

use App\AI\PlayNextRecommendationService;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

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
